<?php

namespace App\Services\Ai\Tools;

use App\Models\User;

/**
 * A tool that changes something.
 *
 * Writing tools are never executed by the model's say-so. The loop turns a
 * call into a *proposal*: the assistant describes what it is about to do, the
 * administrator presses Confirm, and only then does `perform()` run.
 *
 * That is not ceremony. The whole assistant is driven by a model that picks
 * the wrong tool sometimes and can be pointed at a tool by text it read in a
 * record — harmless while everything is read-only, and not harmless the moment
 * one of them writes. A human approval step means the worst a bad call can do
 * is show a proposal nobody accepts.
 *
 * `run()` from the parent interface is therefore never called for these; the
 * Agent checks for this interface first. Implement it by returning the same
 * text `summarise()` does, so a future caller that misses the distinction
 * describes rather than writes.
 */
interface WriteTool extends AgentTool
{
    /**
     * One sentence naming exactly what will happen, shown on the confirm card.
     *
     * The administrator approves on the strength of this line, so it has to
     * carry the specifics — "Create the page 'Refund Policy' at /p/refunds",
     * not "Create a page".
     *
     * @param  array<string, mixed>  $input  already validated by validate()
     */
    public function summarise(array $input, User $admin): string;

    /**
     * Check the input the model produced, before it is ever shown or stored.
     *
     * Returns an error string for the model to read and retry, or null when
     * the input is good. Validating at proposal time rather than at confirm
     * time means the administrator is never shown a plausible sentence that
     * cannot actually be carried out.
     *
     * @param  array<string, mixed>  $input
     */
    public function validate(array $input, User $admin): ?string;

    /**
     * Do it. Only ever called after the administrator confirms.
     *
     * @param  array<string, mixed>  $input
     * @return string what happened, for the transcript
     */
    public function perform(array $input, User $admin): string;
}
