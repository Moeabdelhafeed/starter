<?php

namespace App\Services\Ai\Tools;

use App\Helpers\ProtectedPages;
use App\Models\Page;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Publish or unpublish a content page.
 *
 * Chosen as the second writing tool over deleting because it is the thing
 * actually asked for day to day, and because it is reversible — the wrong
 * answer here is a page hidden for a minute, not a page gone.
 *
 * It also has to honour the rule that makes protected pages protected:
 * **unpublishing one counts as deleting it.** The API's `show()` filters on
 * `active()`, so an inactive Terms page answers 404 to a shipped mobile build
 * exactly like a missing one. `Page`'s own `saving` guard already forces
 * `is_active` back to true, so an attempt would silently do nothing — which is
 * the worst outcome, because the assistant would report success. This refuses
 * at proposal time and says why.
 */
final class SetPageStatusTool implements WriteTool
{
    public function name(): string
    {
        return 'set_page_status';
    }

    public function description(): string
    {
        return 'Publish or unpublish a content page, so it is visible or hidden. The administrator has to confirm. Give the page title or its web address.';
    }

    public function schema(User $admin): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'page' => [
                    'type' => 'string',
                    'description' => 'The page title or its web address, e.g. "Refund Policy" or "refund-policy".',
                ],
                'published' => [
                    'type' => 'boolean',
                    'description' => 'true to publish it, false to hide it.',
                ],
            ],
            'required' => ['page', 'published'],
            'additionalProperties' => false,
        ];
    }

    public function availableTo(User $admin): bool
    {
        return (bool) config('features.pages') && $admin->can('pages');
    }

    public function validate(array $input, User $admin): ?string
    {
        $validator = Validator::make($input, [
            'page' => ['required', 'string', 'max:255'],
            'published' => ['required', 'boolean'],
        ]);

        if ($validator->fails()) {
            return 'Cannot do that: '.implode(' ', $validator->errors()->all());
        }

        $page = $this->find((string) $input['page']);

        if ($page === null) {
            return 'There is no page called "'.$input['page'].'". Check the list of pages first.';
        }

        // Refused here rather than at perform(): the model's own saving guard
        // would quietly force it back on, and a change that silently does
        // nothing while the assistant reports success is the worse failure.
        if (! $input['published'] && ProtectedPages::has((string) $page->slug)) {
            return 'The page "'.$this->label($page).'" cannot be hidden — the mobile app links to it directly, so hiding it would break that screen.';
        }

        if ((bool) $page->is_active === (bool) $input['published']) {
            return 'The page "'.$this->label($page).'" is already '.($input['published'] ? 'published' : 'hidden').'.';
        }

        return null;
    }

    public function summarise(array $input, User $admin): string
    {
        $page = $this->find((string) $input['page']);

        return ($input['published'] ? 'Publish' : 'Hide').' the page "'.$this->label($page).'".';
    }

    public function perform(array $input, User $admin): string
    {
        if (! $this->availableTo($admin)) {
            return 'That is no longer allowed.';
        }

        // Re-run in full: the page may have been renamed, deleted, protected or
        // already toggled between the proposal and the confirmation.
        if (($error = $this->validate($input, $admin)) !== null) {
            return $error;
        }

        $page = $this->find((string) $input['page']);
        $page->update(['is_active' => (bool) $input['published']]);

        return 'The page "'.$this->label($page).'" is now '.($input['published'] ? 'published' : 'hidden').'.';
    }

    public function run(array $input, User $admin): string
    {
        return $this->summarise($input, $admin);
    }

    /**
     * Match on the slug first, then on a title in any language.
     *
     * Admins say "the refund policy page", not "refund-policy", and the model
     * passes through whichever it was given.
     */
    private function find(string $needle): ?Page
    {
        $needle = trim($needle);

        return Page::with('translations')
            ->where('slug', $needle)
            ->orWhere('slug', Str::slug($needle))
            ->orWhereHas('translations', fn ($q) => $q->where('field', 'name')->where('value', $needle))
            ->first();
    }

    private function label(?Page $page): string
    {
        return $page === null ? '' : ($page->name_api ?: $page->slug);
    }
}
