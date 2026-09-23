<?php

namespace App\Services\Ai;

/**
 * A model backend.
 *
 * Everything else in App\Services\Ai — the tool registry, the permission
 * gating, the loop, the audit trail — is provider-agnostic and must stay that
 * way. Only the HTTP call and the response parsing live behind this interface,
 * so adding a hosted provider later is one class rather than a second
 * implementation of the agent.
 */
interface AgentDriver
{
    /**
     * Send the conversation and get one turn back.
     *
     * `$messages` is the accumulated transcript in OpenAI-compatible shape:
     * `{role: system|user|assistant|tool, content: string, ...}`. `$tools` is
     * the JSON-Schema tool list the caller has already filtered down to what
     * this admin is allowed to use.
     *
     * @param  array<int, array<string, mixed>>  $messages
     * @param  array<int, array<string, mixed>>  $tools
     *
     * @throws \RuntimeException when the backend is unreachable or answers badly
     */
    public function chat(array $messages, array $tools): AgentReply;

    /**
     * The same turn, but calling `$onToken` with each fragment as it arrives.
     *
     * A local model takes tens of seconds; without this the panel shows a
     * spinner for the whole turn and a slow answer is indistinguishable from a
     * hung one. Returns the same assembled reply, so the loop does not care
     * which path it used.
     *
     * The second argument marks a fragment as the model's own reasoning rather
     * than the answer. It is passed on so the caller can keep the connection
     * fed through a long think, but it is not part of the reply and must never
     * be shown as one.
     *
     * @param  array<int, array<string, mixed>>  $messages
     * @param  array<int, array<string, mixed>>  $tools
     * @param  callable(string, bool): void  $onToken
     *
     * @throws \RuntimeException when the backend is unreachable or answers badly
     */
    public function chatStream(array $messages, array $tools, callable $onToken): AgentReply;

    /** Whether this driver is configured enough to be worth calling. */
    public function configured(): bool;

    /** Short label for the UI and for error messages, e.g. "Ollama (llama3.2)". */
    public function label(): string;
}
