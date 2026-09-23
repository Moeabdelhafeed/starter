<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;
use Psr\Http\Message\StreamInterface;
use RuntimeException;

/**
 * Ollama, over its OpenAI-compatible endpoint (`/v1/chat/completions`).
 *
 * That endpoint rather than Ollama's native `/api/chat` on purpose: the same
 * wire format is spoken by LM Studio, vLLM, OpenRouter, Groq and together.ai,
 * so this one class covers every OpenAI-compatible backend. Point `OLLAMA_URL`
 * at any of them.
 */
final class OllamaDriver implements AgentDriver
{
    public function configured(): bool
    {
        return filled(config('ai.ollama.url')) && filled(config('ai.ollama.model'));
    }

    public function label(): string
    {
        return 'Ollama ('.config('ai.ollama.model').')';
    }

    public function chat(array $messages, array $tools): AgentReply
    {
        $payload = [
            'model' => (string) config('ai.ollama.model'),
            'messages' => $messages,
            'stream' => false,
            // Ollama defaults to 0.8, which is tuned for conversation. This is
            // not conversation: the model has to pick one tool from a list and
            // read a number back without embellishing it. Sampling that freely
            // is how a correct tool result becomes "no record of it exists".
            'temperature' => (float) config('ai.ollama.temperature'),
        ];

        // An empty `tools` array is not the same as omitting it: some backends
        // reject the empty list rather than reading it as "no tools".
        if ($tools !== []) {
            $payload['tools'] = array_map(
                fn (array $tool): array => ['type' => 'function', 'function' => $tool],
                $tools,
            );
        }

        $response = Http::timeout((int) config('ai.ollama.timeout'))
            ->acceptJson()
            ->post(rtrim((string) config('ai.ollama.url'), '/').'/chat/completions', $payload);

        if ($response->failed()) {
            throw new RuntimeException(
                'The model backend answered '.$response->status().'. Check that Ollama is running and that '
                .config('ai.ollama.model').' is pulled.'
            );
        }

        $message = $response->json('choices.0.message');

        if (! is_array($message)) {
            throw new RuntimeException('The model backend returned no message.');
        }

        $content = $this->withoutReasoning((string) ($message['content'] ?? ''));
        $calls = $this->toolCalls($message);

        // Small models sometimes *write* the tool call instead of emitting one —
        // the reply arrives as `{"name":"search_records","parameters":{...}}` in
        // the text, with no tool_calls at all. Left alone the admin is shown raw
        // JSON and the call never runs. Recovering it is the difference between
        // a working assistant and one that leaks its own plumbing.
        if ($calls === [] && ($recovered = $this->toolCallFromText($content)) !== null) {
            return new AgentReply(content: '', toolCalls: [$recovered]);
        }

        return new AgentReply(content: $content, toolCalls: $calls);
    }

    public function chatStream(array $messages, array $tools, callable $onToken): AgentReply
    {
        $content = '';
        /** @var array<int, array{id: string, name: string, arguments: string}> $calls */
        $calls = [];
        // Reasoning models stream their scratchpad as ordinary tokens, so the
        // <think> block has to be suppressed live rather than stripped at the
        // end — otherwise the admin watches it being written.
        $thinking = false;

        $response = Http::timeout((int) config('ai.ollama.timeout'))
            ->withOptions(['stream' => true])
            ->post(rtrim((string) config('ai.ollama.url'), '/').'/chat/completions', [
                'model' => (string) config('ai.ollama.model'),
                'messages' => $messages,
                'stream' => true,
                'temperature' => (float) config('ai.ollama.temperature'),
            ] + ($tools === [] ? [] : ['tools' => array_map(
                fn (array $tool): array => ['type' => 'function', 'function' => $tool],
                $tools,
            )]));

        if ($response->failed()) {
            throw new RuntimeException('The model backend answered '.$response->status().'.');
        }

        foreach ($this->sseChunks($response->toPsrResponse()->getBody()) as $chunk) {
            $delta = $chunk['choices'][0]['delta'] ?? [];

            foreach ($delta['tool_calls'] ?? [] as $part) {
                // Arguments arrive split across chunks, keyed by index.
                $index = (int) ($part['index'] ?? 0);
                $calls[$index]['id'] ??= (string) ($part['id'] ?? 'call_'.$index);
                $calls[$index]['name'] ??= (string) ($part['function']['name'] ?? '');
                $calls[$index]['arguments'] = ($calls[$index]['arguments'] ?? '').($part['function']['arguments'] ?? '');
            }

            $text = (string) ($delta['content'] ?? '');

            if ($text === '') {
                continue;
            }

            $content .= $text;

            if (str_contains($text, '<think>')) {
                $thinking = true;
            }

            // Passed on either way, flagged. A reasoning model can spend ten
            // seconds inside <think> before the answer starts; sending nothing
            // for that long is exactly the silence streaming exists to avoid,
            // and any proxy in the path reads it as a dead connection.
            $onToken($text, $thinking);

            if (str_contains($text, '</think>')) {
                $thinking = false;
            }
        }

        $assembled = [
            'content' => $content,
            'tool_calls' => array_values(array_map(fn (array $call): array => [
                'id' => $call['id'],
                'type' => 'function',
                'function' => ['name' => $call['name'] ?? '', 'arguments' => $call['arguments'] ?? ''],
            ], $calls)),
        ];

        $clean = $this->withoutReasoning($content);
        $parsed = $this->toolCalls($assembled);

        if ($parsed === [] && ($recovered = $this->toolCallFromText($clean)) !== null) {
            return new AgentReply(content: '', toolCalls: [$recovered]);
        }

        return new AgentReply(content: $clean, toolCalls: $parsed);
    }

    /**
     * Decode an OpenAI-style SSE body into chunks.
     *
     * Read in small reads and split on newlines rather than trusting one read
     * to contain whole lines — a chunk boundary lands mid-JSON often enough
     * that assuming otherwise drops tokens silently.
     *
     * @return \Generator<int, array<string, mixed>>
     */
    private function sseChunks(StreamInterface $body): \Generator
    {
        $buffer = '';

        while (! $body->eof()) {
            $buffer .= $body->read(1024);

            while (($newline = strpos($buffer, "\n")) !== false) {
                $line = trim(substr($buffer, 0, $newline));
                $buffer = substr($buffer, $newline + 1);

                if (! str_starts_with($line, 'data:')) {
                    continue;
                }

                $payload = trim(substr($line, 5));

                if ($payload === '' || $payload === '[DONE]') {
                    continue;
                }

                $decoded = json_decode($payload, true);

                if (is_array($decoded)) {
                    yield $decoded;
                }
            }
        }
    }

    /**
     * Drop a reasoning model's scratchpad.
     *
     * Qwen3 and friends wrap their working in `<think>…</think>` and expect the
     * client to hide it. Left in, the admin reads the model second-guessing
     * itself before the answer — and on a tool turn the block often *is* the
     * whole content. Unclosed blocks happen when a reply is cut short, so an
     * opening tag with no close takes everything after it.
     */
    private function withoutReasoning(string $content): string
    {
        $stripped = preg_replace('#<think>.*?</think>#is', '', $content) ?? $content;
        $stripped = preg_replace('#<think>.*$#is', '', $stripped) ?? $stripped;

        return trim($this->withoutMarkdown($stripped));
    }

    /**
     * Flatten the markdown a chat-tuned model reaches for by habit.
     *
     * The panel renders replies literally, so `**on**` arrives on screen with
     * the asterisks showing. The system prompt asks for plain text and is
     * mostly obeyed — mostly is not a rendering strategy, and this costs two
     * regexes. Only emphasis and links: headings and lists read fine as text,
     * and touching them would mangle a legitimately bulleted answer.
     */
    private function withoutMarkdown(string $content): string
    {
        // [label](url) -> label (url); a bare [label]() keeps just the label.
        $flat = preg_replace('#\[([^\]]+)\]\(([^)]*)\)#', '$1$2' === '' ? '$1' : '$1 ($2)', $content) ?? $content;
        $flat = preg_replace('#\[([^\]]+)\]\(\s*\)#', '$1', $flat) ?? $flat;

        // **bold**, __bold__ — paired only, so a stray asterisk survives.
        return preg_replace('#(\*\*|__)(.+?)\1#s', '$2', $flat) ?? $flat;
    }

    /**
     * Pull a tool call out of the assistant's prose.
     *
     * Deliberately tolerant: the JSON these produce is frequently malformed
     * (`{"name":"x","parameters{...}}`, a missing closing brace), so the name is
     * matched on its own and the arguments are a best effort. A recovered call
     * with empty input still beats showing the admin a JSON blob — the tool's
     * own validation then answers with something the model can read and retry.
     *
     * Only fires when the text is *mostly* the call. A sentence that merely
     * mentions a tool name is left as prose.
     *
     * @return array{id: string, name: string, input: array<string, mixed>}|null
     */
    private function toolCallFromText(string $content): ?array
    {
        $trimmed = trim($content);

        if ($trimmed === '' || ! str_starts_with($trimmed, '{') || ! str_contains($trimmed, '"name"')) {
            return null;
        }

        if (! preg_match('/"name"\s*:\s*"([a-z_]+)"/', $trimmed, $nameMatch)) {
            return null;
        }

        $input = [];

        // `parameters` or `arguments`, whichever the model reached for, and only
        // when the object after it happens to parse.
        if (preg_match('/"(?:parameters|arguments)"\s*:?\s*(\{.*\})/s', $trimmed, $argsMatch)) {
            $decoded = json_decode(rtrim($argsMatch[1], '}').'}', true) ?: json_decode($argsMatch[1], true);
            $input = is_array($decoded) ? $decoded : [];
        }

        return ['id' => 'recovered_'.substr(md5($trimmed), 0, 8), 'name' => $nameMatch[1], 'input' => $input];
    }

    /**
     * @param  array<string, mixed>  $message
     * @return array<int, array{id: string, name: string, input: array<string, mixed>}>
     */
    private function toolCalls(array $message): array
    {
        $calls = [];

        foreach ($message['tool_calls'] ?? [] as $index => $call) {
            $name = $call['function']['name'] ?? null;

            if (! is_string($name) || $name === '') {
                continue;
            }

            // Arguments arrive as a JSON *string*, and a small model will
            // occasionally emit something that is not valid JSON at all. That is
            // a bad tool call, not a crash: hand the loop an empty input and let
            // the tool's own validation produce the error the model reads back.
            $decoded = json_decode((string) ($call['function']['arguments'] ?? ''), true);

            $calls[] = [
                'id' => (string) ($call['id'] ?? 'call_'.$index),
                'name' => $name,
                'input' => is_array($decoded) ? $decoded : [],
            ];
        }

        return $calls;
    }
}
