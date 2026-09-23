<?php

namespace App\Services\Ai;

use App\Models\User;
use App\Services\Ai\Tools\AgentTool;
use App\Services\Ai\Tools\CountRecordsTool;
use App\Services\Ai\Tools\CreatePageTool;
use App\Services\Ai\Tools\ExplainSettingTool;
use App\Services\Ai\Tools\ListRecordsTool;
use App\Services\Ai\Tools\SearchRecordsTool;
use App\Services\Ai\Tools\SetPageStatusTool;

/**
 * Which tools this admin gets.
 *
 * Filtering happens **before** the tool list reaches the model, not after it
 * calls one. An admin without a module's permission is never told that module's
 * tool exists, so there is nothing for a prompt injection to talk the model
 * into reaching for — the same reason the command palette filters server-side
 * rather than hiding rows in the UI.
 *
 * `Gate::before` lets super_admin through every check, as everywhere else.
 */
final class ToolRegistry
{
    /** @var array<int, class-string<AgentTool>> */
    private const TOOLS = [
        CountRecordsTool::class,
        ListRecordsTool::class,
        SearchRecordsTool::class,
        ExplainSettingTool::class,

        // Writing tools. Never executed by the model: the Agent turns a call
        // into a proposal the administrator confirms.
        CreatePageTool::class,
        SetPageStatusTool::class,
    ];

    /**
     * The tools this admin may use.
     *
     * @return array<string, AgentTool> keyed by tool name
     */
    public function for(User $admin): array
    {
        $allowed = [];

        foreach (self::TOOLS as $class) {
            /** @var AgentTool $tool */
            $tool = app($class);

            if ($tool->availableTo($admin)) {
                $allowed[$tool->name()] = $tool;
            }
        }

        return $allowed;
    }

    /**
     * The same set as JSON-Schema definitions for the wire.
     *
     * @param  array<string, AgentTool>  $tools
     * @return array<int, array<string, mixed>>
     */
    public function definitions(array $tools, User $admin): array
    {
        return array_values(array_map(fn (AgentTool $tool): array => [
            'name' => $tool->name(),
            'description' => $tool->description(),
            'parameters' => $tool->schema($admin),
        ], $tools));
    }
}
