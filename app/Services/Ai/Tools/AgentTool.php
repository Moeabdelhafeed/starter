<?php

namespace App\Services\Ai\Tools;

use App\Models\User;

/**
 * One thing the assistant can do.
 *
 * Two rules hold the security model together, and both are the tool's job:
 *
 * 1. **`availableTo()` decides whether the model is even told this tool
 *    exists.** The registry filters by it before the tool list goes on the
 *    wire, so there is nothing for a prompt injection to talk the model into
 *    reaching for. A single-module tool answers `$admin->can('users')`; a tool
 *    spanning several modules answers "any of them", and then re-checks each
 *    module inside `run()` — being offered the tool says nothing about which
 *    modules it may actually read.
 *
 * 2. **`run()` validates its own input.** The arguments come from a language
 *    model, which is an untrusted source however sober the prompt looks, and a
 *    small local model gets schemas wrong routinely. Treat the input exactly
 *    like request input: validate, then use.
 */
interface AgentTool
{
    /** Stable snake_case identifier the model calls. */
    public function name(): string;

    /** One line. This is the only thing telling the model when to reach for it. */
    public function description(): string;

    /**
     * JSON Schema for the arguments, narrowed to what this admin can use.
     *
     * Admin-aware on purpose: a tool spanning several modules must not offer a
     * module whose feature is off or whose permission is missing. Advertising
     * one costs a whole round trip to be refused, and a model told an option
     * exists will keep reaching for it.
     *
     * @return array<string, mixed>
     */
    public function schema(User $admin): array;

    /**
     * Whether this admin is offered the tool at all.
     *
     * Covers both halves of the usual gate: the feature flag being on and the
     * permission being held. `Gate::before` lets super_admin through.
     */
    public function availableTo(User $admin): bool;

    /**
     * Run it and return something the model can read back.
     *
     * @param  array<string, mixed>  $input  unvalidated, model-supplied
     */
    public function run(array $input, User $admin): string;
}
