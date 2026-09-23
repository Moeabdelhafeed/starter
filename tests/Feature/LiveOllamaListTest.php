<?php

use App\Models\Page;
use App\Services\Ai\Agent;

it('lists the CMS pages through the real model', function () {
    if (! env('AI_LIVE_TEST')) {
        $this->markTestSkipped('Set AI_LIVE_TEST=1 to run against a real Ollama.');
    }

    Page::factory()->create(['slug' => 'terms']);
    Page::factory()->create(['slug' => 'privacy']);
    Page::factory()->create(['slug' => 'refunds']);

    $admin = adminWithPermissions(['pages']);

    $result = app(Agent::class)->ask('What pages do we have in the CMS?', $admin);

    fwrite(STDERR, sprintf("\n  tools=[%s]\n  reply: %s\n", implode(', ', $result['tools_used']), $result['reply']));

    // Case-insensitive: the model reliably reports the right pages but titles
    // them as it likes ("refunds", "Refunds", "a Refunds page"). Asserting the
    // exact casing tests the model's prose, not whether the tool worked.
    expect($result['tools_used'])->toContain('list_records')
        ->and(strtolower($result['reply']))->toContain('refunds');
});
