<?php

namespace App\Services\Ai\Tools;

use App\Models\Language;
use App\Models\Page;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Create a content page.
 *
 * The first writing tool, and the shape the rest should copy: it proposes,
 * the administrator confirms, and only then does anything change.
 *
 * Content is deliberately not a parameter. A page's body is written in the
 * editor with the formatting tools and the translation tabs; having a small
 * model draft it into a plain string would produce something worse that then
 * has to be fixed by hand. This creates the page and hands over the link.
 */
final class CreatePageTool implements WriteTool
{
    public function name(): string
    {
        return 'create_page';
    }

    public function description(): string
    {
        return 'Create a new, empty content page with a title and a web address. The administrator has to confirm before it is created. Does not write the page body.';
    }

    public function schema(User $admin): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'title' => [
                    'type' => 'string',
                    'description' => 'The page title, e.g. "Refund Policy".',
                ],
                'slug' => [
                    'type' => 'string',
                    'description' => 'Optional web address, lowercase with hyphens, e.g. "refund-policy". Derived from the title when omitted.',
                ],
            ],
            'required' => ['title'],
            'additionalProperties' => false,
        ];
    }

    public function availableTo(User $admin): bool
    {
        return (bool) config('features.pages') && $admin->can('pages');
    }

    public function validate(array $input, User $admin): ?string
    {
        $validator = Validator::make($this->normalise($input), [
            'title' => ['required', 'string', 'max:255'],
            // Matches PageController@store, so a proposal the administrator
            // accepts cannot then fail the real validation.
            'slug' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'unique:pages,slug'],
        ]);

        if ($validator->fails()) {
            return 'Cannot create that page: '.implode(' ', $validator->errors()->all());
        }

        return null;
    }

    public function summarise(array $input, User $admin): string
    {
        $normalised = $this->normalise($input);

        return 'Create the page "'.$normalised['title'].'" at /p/'.$normalised['slug'].'.';
    }

    public function perform(array $input, User $admin): string
    {
        // Re-checked here, not just when the tool was offered: a confirmation
        // can arrive after the feature was switched off or the permission
        // revoked, and the gate that matters is the one at the moment of the
        // write.
        if (! $this->availableTo($admin)) {
            return 'That is no longer allowed.';
        }

        if (($error = $this->validate($input, $admin)) !== null) {
            return $error;
        }

        $normalised = $this->normalise($input);

        $page = Page::create(['slug' => $normalised['slug'], 'is_active' => true]);

        // A page with no translation row shows an empty title in the CMS list,
        // so the title lands in every active language rather than just the
        // current one — the admin renames the others in the editor.
        $locales = Language::active()->pluck('code');
        $page->saveTranslations([
            'name' => $locales->mapWithKeys(fn (string $code): array => [$code => $normalised['title']])->all(),
        ]);

        return 'Created the page "'.$normalised['title'].'" at /p/'.$page->slug.'. It is empty — open it in Pages to write the content.';
    }

    public function run(array $input, User $admin): string
    {
        // Never writes. The Agent routes WriteTool through propose/confirm; a
        // caller that reaches this has missed the distinction, and describing
        // is the safe reading of "run this tool".
        return $this->summarise($input, $admin);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{title: string, slug: string}
     */
    private function normalise(array $input): array
    {
        $title = trim((string) ($input['title'] ?? ''));
        $slug = trim((string) ($input['slug'] ?? ''));

        return [
            'title' => $title,
            'slug' => $slug !== '' ? Str::slug($slug) : Str::slug($title),
        ];
    }
}
