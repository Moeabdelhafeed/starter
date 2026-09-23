<?php

namespace App\Http\Controllers\Admin\Ai;

use App\Http\Controllers\Controller;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Services\Ai\Agent;
use App\Services\Ai\AgentDriver;
use App\Services\Ai\ToolRegistry;
use App\Services\Ai\Tools\WriteTool;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The admin assistant, with saved chats.
 *
 * Reading tools run on the model's say-so. Writing tools never do: a call
 * becomes a proposal stored on the assistant's message, and `confirm()` is the
 * only thing that carries it out. The permission gating that matters happens in
 * ToolRegistry, which filters the tool list by this admin's permissions before
 * the model is told what exists — and again at confirm time, because approval
 * can arrive after a permission was revoked.
 *
 * **Conversations are always resolved through `$request->user()->aiConversations()`**,
 * never `AiConversation::find()`. A chat holds questions about the business and
 * whatever the assistant read back, so an id from one admin's browser must not
 * open another admin's history — the relation is what makes that a 404 rather
 * than a leak.
 *
 * `stream()` is the endpoint the panel actually uses; `chat()` is the same turn
 * without streaming, kept for anything calling this without an event-source
 * reader. A two-tool turn against a local model takes the better part of a
 * minute, and a minute of nothing is indistinguishable from a hang — every
 * proxy, load balancer and impatient user treats it as one. Streaming is what
 * stops that, not a cosmetic touch.
 *
 * Both write exactly the same rows, so a reload shows the same conversation
 * whichever path produced it.
 */
class AiAgentController extends Controller
{
    /** Turns replayed to the model. Older context costs tokens and adds little. */
    private const HISTORY_TURNS = 20;

    public function index(Request $request, AgentDriver $driver): Response
    {
        return Inertia::render('Ai/Index', [
            'backend' => $driver->label(),
            'backendReady' => $driver->configured(),
            'conversations' => $this->conversationList($request),
        ]);
    }

    /** One chat's turns, for the client to render when it is opened. */
    public function show(Request $request, int $conversation): JsonResponse
    {
        $chat = $request->user()->aiConversations()->with('messages')->findOrFail($conversation);

        return response()->json([
            'id' => $chat->id,
            'title' => $chat->title,
            'messages' => $chat->messages->map(fn ($message): array => [
                'role' => $message->role,
                'content' => $message->content,
                'tools' => $message->tools_used ?? [],
                'pending' => $message->pending_action !== null && $message->performed_at === null
                    ? ['message_id' => $message->id, 'summary' => $message->pending_action['summary']]
                    : null,
            ])->all(),
        ]);
    }

    public function chat(Request $request, Agent $agent): JsonResponse
    {
        $validated = $this->validateTurn($request);
        $chat = $this->resolveConversation($request, $validated);
        $history = $this->history($chat);

        try {
            $result = $agent->ask(
                question: $validated['message'],
                admin: $request->user(),
                history: $history,
            );
        } catch (RuntimeException $e) {
            // The question is not stored when the backend never answered — a
            // half-written chat with a user turn and no reply reads as a bug.
            if ($chat->messages()->doesntExist()) {
                $chat->delete();
            }

            return response()->json(['error' => $e->getMessage()], 422);
        }

        $answer = $this->record($chat, $validated['message'], $result);

        return response()->json($this->turnPayload($request, $chat, $answer, $result));
    }

    /**
     * The same turn, as Server-Sent Events.
     *
     * Three things this has to get right, each of which broke something when
     * it was missing:
     *
     * - **`session()->save()` first.** The session file is locked for the whole
     *   request, so a turn that runs for 40 seconds blocks *every other
     *   request from that admin* for 40 seconds — the panel appears frozen,
     *   which is the exact symptom streaming was meant to cure.
     * - **`X-Accel-Buffering: no` and flushing by hand.** nginx buffers a
     *   proxied response by default and PHP has its own buffers on top; with
     *   either in the way the whole stream arrives at once at the end, which
     *   is just the unstreamed version with extra code.
     * - **The rows are written inside the stream, before the `done` event.** A
     *   client that reloads mid-answer gets a conversation that matches what
     *   it saw, and the `pending` proposal reaches the confirm card the same
     *   way it does on the plain endpoint.
     *
     * Errors are an `error` event rather than a status code: the response has
     * already committed 200 by the time the model is called.
     */
    public function stream(Request $request, Agent $agent): StreamedResponse
    {
        $validated = $this->validateTurn($request);
        $chat = $this->resolveConversation($request, $validated);
        $history = $this->history($chat);
        $admin = $request->user();

        // Nothing below may touch the session: it is closed from here on.
        session()->save();

        return response()->stream(function () use ($agent, $admin, $chat, $validated, $request, $history): void {
            $send = function (array $event): void {
                echo 'data: '.json_encode($event)."\n\n";

                // `ob_flush()` then `flush()`, matching what the framework's
                // own `eventStream()` does: PHP's output_buffering holds the
                // frame otherwise and the whole stream lands at the end, which
                // is the unstreamed version with extra code.
                if (ob_get_level() > 0) {
                    ob_flush();
                }

                flush();
            };

            try {
                $result = $agent->ask(
                    question: $validated['message'],
                    admin: $admin,
                    history: $history,
                    emit: $send,
                );
            } catch (RuntimeException $e) {
                if ($chat->messages()->doesntExist()) {
                    $chat->delete();
                }

                $send(['type' => 'error', 'message' => $e->getMessage()]);

                return;
            }

            $answer = $this->record($chat, $validated['message'], $result);

            $send(['type' => 'done'] + $this->turnPayload($request, $chat, $answer, $result));
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-transform',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * @return array{message: string, conversation_id?: int|null}
     */
    private function validateTurn(Request $request): array
    {
        return $request->validate([
            'message' => ['required', 'string', 'max:'.config('ai.max_prompt_chars')],
            // Absent starts a new chat. Present, it must be one of this admin's.
            'conversation_id' => ['nullable', 'integer'],
        ]);
    }

    /** @param  array{message: string, conversation_id?: int|null}  $validated */
    private function resolveConversation(Request $request, array $validated): AiConversation
    {
        return isset($validated['conversation_id'])
            ? $request->user()->aiConversations()->findOrFail($validated['conversation_id'])
            : $request->user()->aiConversations()->create([
                'title' => AiConversation::titleFrom($validated['message']),
            ]);
    }

    /**
     * Store the turn and bump `updated_at`, so the sidebar orders by last used.
     *
     * @param  array{reply: string, tools_used: array<int, string>, pending: ?array<string, mixed>}  $result
     */
    private function record(AiConversation $chat, string $question, array $result): AiMessage
    {
        $chat->messages()->create(['role' => 'user', 'content' => $question]);

        $answer = $chat->messages()->create([
            'role' => 'assistant',
            'content' => $result['reply'],
            'tools_used' => $result['tools_used'],
            // Held server-side. The confirm request names this message; what
            // runs is what this row says, never what the browser sends back.
            'pending_action' => $result['pending'],
        ]);

        $chat->touch();

        return $answer;
    }

    /**
     * @param  array{reply: string, tools_used: array<int, string>, pending: ?array<string, mixed>}  $result
     * @return array<string, mixed>
     */
    private function turnPayload(Request $request, AiConversation $chat, AiMessage $answer, array $result): array
    {
        return [
            'reply' => $result['reply'],
            'tools_used' => $result['tools_used'],
            // Only the summary and the message id cross the wire — enough to
            // render the card, nothing the client could tamper into a
            // different action.
            'pending' => $result['pending'] === null ? null : [
                'message_id' => $answer->id,
                'summary' => $result['pending']['summary'],
            ],
            'conversation' => ['id' => $chat->id, 'title' => $chat->title],
            'conversations' => $this->conversationList($request),
        ];
    }

    /**
     * Carry out a change the assistant proposed and the administrator approved.
     *
     * The action is read from the stored message, never from the request: the
     * client sends an id and nothing else, so approving cannot be turned into
     * approving something different. `performed_at` makes it idempotent — a
     * double-click or a stale tab cannot run the same write twice.
     */
    public function confirm(Request $request, int $message, ToolRegistry $registry): JsonResponse
    {
        $admin = $request->user();

        $answer = AiMessage::query()
            ->whereIn('ai_conversation_id', $admin->aiConversations()->select('id'))
            ->findOrFail($message);

        if ($answer->pending_action === null) {
            return response()->json(['error' => __('admin.nothing_to_confirm')], 422);
        }

        if ($answer->performed_at !== null) {
            return response()->json(['error' => __('admin.already_done')], 422);
        }

        // Re-resolved through the registry, so a tool the admin may no longer
        // use — permission revoked, feature switched off since the proposal —
        // is simply not there to run.
        $tool = $registry->for($admin)[$answer->pending_action['tool']] ?? null;

        if (! $tool instanceof WriteTool) {
            return response()->json(['error' => __('admin.nothing_to_confirm')], 422);
        }

        $outcome = $tool->perform($answer->pending_action['input'], $admin);

        $answer->forceFill(['performed_at' => now()])->save();

        $note = $answer->conversation->messages()->create(['role' => 'assistant', 'content' => $outcome]);
        $answer->conversation->touch();

        return response()->json(['reply' => $note->content]);
    }

    public function destroy(Request $request, int $conversation): RedirectResponse
    {
        $request->user()->aiConversations()->findOrFail($conversation)->delete();

        return back()->with('success', __('admin.deleted_successfully'));
    }

    /**
     * The turns replayed to the model, oldest first.
     *
     * Read back from the database, not replayed by the browser. The client used
     * to send it, which made the transcript the client's to rewrite — a forged
     * `assistant` turn could put words in the model's mouth, and the only
     * defence was validating roles. Now it cannot.
     *
     * `reorder()` first: the relation already carries `orderBy('id')`, so a bare
     * `latest('id')` appends a second clause the database ignores — the limit
     * then took the *oldest* turns and `reverse()` handed the model the
     * conversation backwards.
     *
     * @return array<int, array{role: string, content: string}>
     */
    private function history(AiConversation $chat): array
    {
        return $chat->messages()
            ->reorder()
            ->latest('id')
            ->limit(self::HISTORY_TURNS)
            ->get()
            ->reverse()
            ->map(fn (AiMessage $message): array => ['role' => $message->role, 'content' => $message->content])
            ->values()
            ->all();
    }

    /**
     * The sidebar's list.
     *
     * @return array<int, array{id: int, title: ?string, updated_at: ?string}>
     */
    private function conversationList(Request $request): array
    {
        return $request->user()->aiConversations()
            ->limit(50)
            ->get(['id', 'title', 'updated_at'])
            ->map(fn (AiConversation $chat): array => [
                'id' => $chat->id,
                'title' => $chat->title,
                'updated_at' => $chat->updated_at?->toIso8601String(),
            ])
            ->all();
    }
}
