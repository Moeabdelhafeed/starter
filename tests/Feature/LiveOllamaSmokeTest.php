<?php

use App\Models\Page;
use App\Services\Ai\Agent;

/**
 * Talks to the real Ollama, and is the only test here that does.
 *
 * Opt-in via `AI_LIVE_TEST=1 php artisan test --filter=LiveOllamaSmoke`, not
 * "run it whenever a daemon happens to be listening": a real model is slow and
 * non-deterministic, and a small one will occasionally answer a counting
 * question from its own head instead of calling the tool. That is worth
 * knowing deliberately and worthless as a random red build.
 *
 * Everything else about the assistant is pinned in AiAgentTest with the model
 * faked. This checks the one thing a fake cannot: that a real model, given
 * these schemas, actually drives the tools.
 */
it('answers a counting question through the real model', function () {
    if (! env('AI_LIVE_TEST')) {
        $this->markTestSkipped('Set AI_LIVE_TEST=1 to run against a real Ollama.');
    }

    $up = @fsockopen('127.0.0.1', 11434, $e, $s, 1);
    if (! $up) {
        $this->markTestSkipped('No Ollama on 127.0.0.1:11434.');
    }
    fclose($up);

    Page::factory()->count(7)->create();
    $admin = adminWithPermissions(['pages']);

    $t0 = microtime(true);
    $result = app(Agent::class)->ask('How many pages are on this site?', $admin);
    $elapsed = microtime(true) - $t0;

    fwrite(STDERR, sprintf(
        "\n  model=%s  %.1fs  tools=[%s]\n  reply: %s\n",
        config('ai.ollama.model'), $elapsed,
        implode(', ', $result['tools_used']), $result['reply'],
    ));

    expect($result['tools_used'])->toContain('count_records')
        ->and($result['reply'])->toContain('7');
});
