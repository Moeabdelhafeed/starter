<?php

namespace App\Services\Ai\Tools;

use App\Models\MediaItem;
use App\Models\NotificationTemplate;
use App\Models\Page;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Validator;

/**
 * Name the rows in a module: "what pages do we have", "which roles exist".
 *
 * The obvious companion to counting, and its absence was not harmless — asked
 * to list the CMS pages with only `count_records` and `search_records`
 * available, a small model invented a plausible set (About, Contact, Terms…)
 * rather than saying it could not answer. A missing tool does not read as a
 * missing tool; it reads as a confident wrong answer.
 *
 * Labels only, never content: enough to answer "what have we got", and a
 * deliberately small surface. Each module is permission-gated separately, so
 * the list only ever names rows from a screen this admin could open.
 */
final class ListRecordsTool implements AgentTool
{
    /** Rows returned per call. Enough to answer, short enough to stay cheap. */
    private const LIMIT = 50;

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
    ];

    public function name(): string
    {
        return 'list_records';
    }

    public function description(): string
    {
        return 'List the records in one admin module by name — pages, roles, users, media keys or notification templates. Use this for "what/which ... do we have" questions.';
    }

    public function schema(User $admin): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'module' => [
                    'type' => 'string',
                    'enum' => $this->allowedModules($admin),
                    'description' => 'Which module to list.',
                ],
            ],
            'required' => ['module'],
            'additionalProperties' => false,
        ];
    }

    public function availableTo(User $admin): bool
    {
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

        // Re-checked per call: this tool spans several modules, so being offered
        // it settles nothing about this particular one.
        if ($flag !== null && ! config($flag)) {
            return "The {$module} module is switched off on this install.";
        }

        if (! $admin->can($permission)) {
            return "You do not have permission to see {$module}.";
        }

        $rows = $this->rows($module);

        if ($rows === []) {
            return "There are no {$module}.";
        }

        return $module.' ('.count($rows)." shown):\n- ".implode("\n- ", $rows);
    }

    /** @return array<int, string> */
    private function rows(string $module): array
    {
        return match ($module) {
            // Admin users are web-guard role holders, app users the API guard;
            // one table, so an unscoped query reports each as the other.
            'admin_users' => User::whereHas('roles', fn ($q) => $q->where('guard_name', 'web'))
                ->limit(self::LIMIT)->pluck('email', 'name')
                ->map(fn (string $email, string $name): string => "{$name} ({$email})")->values()->all(),

            'app_users' => User::whereHas('roles', fn ($q) => $q->where('guard_name', 'api'))
                ->limit(self::LIMIT)->pluck('name')->map(fn (?string $n): string => $n ?: '(no name)')->all(),

            'roles' => Role::where('guard_name', 'web')->limit(self::LIMIT)->pluck('name')->all(),

            // name_api is the current locale's title; slug is the URL and the
            // thing PROTECTED_PAGES matches on, so both earn their place.
            'pages' => Page::with('translations')->limit(self::LIMIT)->get()
                ->map(fn (Page $page): string => ($page->name_api ?: $page->slug).' — /p/'.$page->slug)->all(),

            'media' => MediaItem::limit(self::LIMIT)->get()
                ->map(fn (MediaItem $item): string => $item->key.' ('.$item->group.'/'.$item->sub_group.')')->all(),

            'notification_templates' => NotificationTemplate::limit(self::LIMIT)->pluck('slug')->all(),
        };
    }
}
