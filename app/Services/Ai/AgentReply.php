<?php

namespace App\Services\Ai;

/**
 * One model turn, normalised away from whichever provider produced it.
 *
 * Providers disagree on shape — OpenAI-compatible backends nest tool calls under
 * `choices[0].message.tool_calls` with the arguments as a JSON *string*, Anthropic
 * returns typed `tool_use` content blocks. The loop should not know or care, so
 * every driver returns this.
 */
final readonly class AgentReply
{
    /**
     * @param  string  $content  the assistant's prose, empty on a pure tool turn
     * @param  array<int, array{id: string, name: string, input: array<string, mixed>}>  $toolCalls
     */
    public function __construct(
        public string $content,
        public array $toolCalls = [],
    ) {}

    public function wantsTools(): bool
    {
        return $this->toolCalls !== [];
    }
}
