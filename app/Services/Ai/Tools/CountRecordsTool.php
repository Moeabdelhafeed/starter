<?php

namespace App\Services\Ai\Tools;

use App\Models\ActivityLog;
use App\Models\MediaItem;
use App\Models\NotificationTemplate;
use App\Models\Page;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Validator;

/**
 * How many rows a module holds.
 *
 * The single most common question an admin asks that the UI answers badly —
 * "how many app users are there" currently means opening a list and reading a
 * paginator. It is also the safest possible tool: one integer, no row data.
 *
 * Each module is gated separately, so the count the assistant reports is only
 * ever from a screen this admin could open themselves.
 */
final class CountRecordsTool implements AgentTool
{
    /**
     * module => [permission, feature flag (null when always on)]
     *
     * @var array<string, array{0: string, 1: ?string}>
     */
    private const MODULES = [
        'admin_users' => ['users', null],
        'app_users' => ['app_users', 'features.app_users'],
        'roles' => ['roles', null],
        'pages' => ['pages', 'features.pages'],
        'media' => ['dynamic_storage', 'features.dynamic_storage'],
        'notification_templates' => ['notification_templates', 'features.notification_templates'],
        'activity_logs' => ['activity_logs', 'features.activity_logs'],
    ];

    public function name(): string
    {
        return 'count_records';
    }

    public function description(): string
    {
        return 'Count the rows in one admin module. Use this for any "how many" question instead of guessing.';
    }

    public function schema(User $admin): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'module' => [
                    'type' => 'string',
                    'enum' => $this->allowedModules($admin),
                    'description' => 'Which module to count.',
                ],
            ],
            'required' => ['module'],
            'additionalProperties' => false,
        ];
    }

    public function availableTo(User $admin): bool
    {
        // Offered when the admin can count *something*. Which modules they may
        // actually read is re-checked per call in run(), because this tool
        // spans several and being offered it settles none of them.
        return $this->allowedModules($admin) !== [];
    }

    /**
     * The modules this admin can actually use, for the schema's enum.
     *
     * Offering one they cannot reach costs a round trip to be refused, and a
     * model told an option exists keeps reaching for it. `run()` still
     * re-checks — the enum is a hint to the model, not a security boundary.
     *
     * @return array<int, string>
     */
    private function allowedModules(User $admin): array
    {
        $allowed = [];

        foreach (self::MODULES as $module => [$permission, $flag]) {
            if (($flag === null || config($flag)) && $admin->can($permission)) {
                $allowed[] = $module;
            }
        }

        return $allowed;
    }

    public function run(array $input, User $admin): string
    {
        $validator = Validator::make($input, [
            'module' => ['required', 'string', 'in:'.implode(',', array_keys(self::MODULES))],
        ]);

        if ($validator->fails()) {
            return 'Error: `module` must be one of '.implode(', ', array_keys(self::MODULES)).'.';
        }

        $module = $input['module'];
        [$permission, $flag] = self::MODULES[$module];

        // Re-checked here, not just at registry time: this tool covers several
        // modules behind one permission, so the offered-ness of the tool says
        // nothing about this particular module.
        if ($flag !== null && ! config($flag)) {
            return "The {$module} module is switched off on this install.";
        }

        if (! $admin->can($permission)) {
            return "You do not have permission to see {$module}.";
        }

        return $module.': '.$this->count($module);
    }

    private function count(string $module): int
    {
        return match ($module) {
            // Admin users are web-guard role holders; app users are the API
            // guard. The two share one table, so counting it plainly would
            // report each module's figure as the other's.
            'admin_users' => User::whereHas('roles', fn ($q) => $q->where('guard_name', 'web'))->count(),
            'app_users' => User::whereHas('roles', fn ($q) => $q->where('guard_name', 'api'))->count(),
            'roles' => Role::where('guard_name', 'web')->count(),
            'pages' => Page::count(),
            'media' => MediaItem::count(),
            'notification_templates' => NotificationTemplate::count(),
            'activity_logs' => ActivityLog::count(),
        };
    }
}
