<?php

namespace App\Services\Ai\Tools;

use App\Http\Controllers\Admin\Search\SearchController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Find records by name across every module the admin can open.
 *
 * Delegates to SearchController — the command palette's endpoint — rather than
 * querying anything itself. That controller already filters every module by
 * feature flag AND permission, escapes LIKE wildcards, and caps the term. A
 * second search implementation here would be a second place for those rules to
 * be got wrong.
 */
final class SearchRecordsTool implements AgentTool
{
    public function __construct(private readonly SearchController $search) {}

    public function name(): string
    {
        return 'search_records';
    }

    public function description(): string
    {
        return 'Search admin records by name, email or title across all modules. Returns matches with links.';
    }

    public function schema(User $admin): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'term' => [
                    'type' => 'string',
                    'description' => 'What to search for, e.g. a name or an email address.',
                ],
            ],
            'required' => ['term'],
            'additionalProperties' => false,
        ];
    }

    public function availableTo(User $admin): bool
    {
        // No gate of its own: SearchController returns only the modules this
        // admin holds the permission for, so an admin with none gets an empty
        // result rather than anything they should not see.
        return true;
    }

    public function run(array $input, User $admin): string
    {
        $validator = Validator::make($input, [
            'term' => ['required', 'string', 'min:2', 'max:100'],
        ]);

        if ($validator->fails()) {
            return 'Error: `term` must be between 2 and 100 characters.';
        }

        // SearchController gates every module on `request()->user()` — the
        // *ambient* request, not the one it is handed. Relying on that being
        // the signed-in admin made this tool silently answer "No records match"
        // anywhere the ambient request has no user (a console run, a queued
        // job, a test), which reads as "the data isn't there" rather than as a
        // wiring fault. Bind the probe request for the duration instead.
        // Note it has to be the *ambient* request's resolver, not a resolver on
        // the probe: `request()` inside the controller resolves the container's
        // request, and rebinding that key does not change what the helper
        // returns. In a real browser request this is already the same admin, so
        // it is a no-op there and a correctness fix everywhere else.
        $ambient = request();
        $previousResolver = $ambient->getUserResolver();
        $ambient->setUserResolver(fn (): User => $admin);

        try {
            $response = ($this->search)(Request::create('/search', 'GET', ['q' => $input['term']]));
        } finally {
            $ambient->setUserResolver($previousResolver);
        }

        /** @var array{groups: array<int, array{key: string, items: array<int, array<string, mixed>>}>} $payload */
        $payload = $response->getData(true);

        if ($payload['groups'] === []) {
            return 'No records match "'.$input['term'].'".';
        }

        $lines = [];

        foreach ($payload['groups'] as $group) {
            foreach ($group['items'] as $item) {
                $subtitle = $item['subtitle'] ?? null;
                $lines[] = '- ['.$group['key'].'] '.$item['title'].($subtitle ? ' ('.$subtitle.')' : '');
            }
        }

        return implode("\n", $lines);
    }
}
