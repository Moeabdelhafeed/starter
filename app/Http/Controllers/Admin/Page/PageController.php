<?php

namespace App\Http\Controllers\Admin\Page;

use App\Http\Controllers\Controller;
use App\Models\Language;
use App\Models\Page;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class PageController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');
        $isActive = $request->input('is_active');

        $pages = Page::query()
            ->with(['translations', 'image'])
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('slug', 'like', "%{$search}%")
                        ->orWhereHas('translations', function ($tq) use ($search) {
                            $tq->where('field', 'name')
                                ->where('value', 'like', "%{$search}%");
                        });
                });
            })
            ->when($isActive !== null && $isActive !== 'all', function ($query) use ($isActive) {
                $query->where('is_active', $isActive);
            })
            ->latest()
            ->scrollPaginate(10);

        $languages = Language::active()->get(['id', 'code', 'name', 'native_name', 'direction']);

        return Inertia::render('Page/Index', [
            'pages' => Inertia::scroll($pages),
            'languages' => $languages,
            'filters' => [
                'search' => $search,
                'is_active' => $isActive,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'slug' => ['required', 'string', 'max:255', 'unique:pages,slug'],
            'is_active' => ['boolean'],
            'image' => ['nullable', 'image', 'max:2048'],
            'translations' => ['required', 'array'],
            'translations.name' => ['required', 'array'],
            'translations.name.*' => ['nullable', 'string', 'max:255'],
            'translations.content' => ['nullable', 'array'],
            'translations.content.*' => ['nullable', 'string'],
        ]);

        $page = Page::create([
            'slug' => $validated['slug'],
            'is_active' => $request->boolean('is_active', true),
        ]);

        $page->saveTranslations($this->sanitizeTranslations($validated['translations']));

        if ($request->hasFile('image')) {
            $page->saveImage($request->file('image'), 'pages');
        }

        return redirect()->route('pages.edit', $page)->with('success', __('admin.created_successfully'));
    }

    public function edit(Page $page)
    {
        $page->load(['translations', 'image']);
        $languages = Language::active()->get(['id', 'code', 'name', 'native_name', 'direction']);

        return Inertia::render('Page/Edit', [
            'page' => $page,
            'languages' => $languages,
        ]);
    }

    public function update(Request $request, Page $page)
    {
        $validated = $request->validate([
            'slug' => ['required', 'string', 'max:255', Rule::unique('pages', 'slug')->ignore($page->id)],
            'is_active' => ['boolean'],
            'image' => ['nullable', 'image', 'max:2048'],
            'remove_image' => ['nullable', 'boolean'],
            'translations' => ['required', 'array'],
            'translations.name' => ['required', 'array'],
            'translations.name.*' => ['nullable', 'string', 'max:255'],
            'translations.content' => ['nullable', 'array'],
            'translations.content.*' => ['nullable', 'string'],
        ]);

        $page->fill([
            // A protected page keeps its slug. Renaming it is how you would otherwise
            // walk out of PROTECTED_PAGES and then delete it — and the mobile build
            // links to the old slug either way.
            'slug' => $page->is_protected ? $page->slug : $validated['slug'],
            'is_active' => $request->boolean('is_active', true),
        ]);

        // The model forces a protected page back to active; say so rather than letting
        // the switch quietly spring back.
        $refusedDeactivation = $page->is_protected && ! $request->boolean('is_active', true);

        $page->save();
        $page->saveTranslations($this->sanitizeTranslations($validated['translations']));

        if ($request->hasFile('image')) {
            $page->saveImage($request->file('image'), 'pages');
        } elseif ($request->boolean('remove_image')) {
            $page->deleteImage();
        }

        if ($refusedDeactivation) {
            return redirect()->back()->with('error', __('admin.page_protected_active'))->with('highlight', $page->id);
        }

        return redirect()->back()->with('success', __('admin.updated_successfully'))->with('highlight', $page->id);
    }

    public function destroy(Page $page)
    {
        if ($page->is_protected) {
            return redirect()->back()->with('error', __('admin.page_protected'));
        }

        $page->deleteImage();
        $page->delete();

        return redirect()->back()->with('success', __('admin.deleted_successfully'));
    }

    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['exists:pages,id'],
        ]);

        // Protected pages are skipped rather than failing the whole selection: the
        // model would refuse them silently, and the admin should be told which.
        $pages = Page::whereIn('id', $validated['ids'])->get();
        $skipped = $pages->filter(fn (Page $page): bool => $page->is_protected);

        foreach ($pages->diff($skipped) as $page) {
            $page->deleteImage();
            $page->delete();
        }

        if ($skipped->isNotEmpty()) {
            return redirect()->back()->with('error', __('admin.pages_protected_skipped', [
                'slugs' => $skipped->pluck('slug')->implode(', '),
            ]));
        }

        return redirect()->back()->with('success', __('admin.deleted_successfully'));
    }

    public function bulkUpdate(Request $request)
    {
        $validated = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['exists:pages,id'],
            'is_active' => ['required', 'boolean'],
        ]);

        $pages = Page::whereIn('id', $validated['ids'])->get();

        // A mass `update()` fires no model events, so the model's guard does not run
        // here: protected pages are filtered out of a deactivation explicitly.
        $skipped = $validated['is_active']
            ? $pages->take(0)
            : $pages->filter(fn (Page $page): bool => $page->is_protected);

        Page::whereIn('id', $pages->diff($skipped)->pluck('id'))->update([
            'is_active' => $validated['is_active'],
        ]);

        if ($skipped->isNotEmpty()) {
            return redirect()->back()->with('error', __('admin.pages_protected_active_skipped', [
                'slugs' => $skipped->pluck('slug')->implode(', '),
            ]));
        }

        return redirect()->back()->with('success', __('admin.updated_successfully'));
    }

    /**
     * Page content is rendered with `v-html` on the same origin as the admin
     * panel, so it is sanitized on write — a page author only needs formatting
     * markup, never script.
     *
     * @param  array{name?: array<string, ?string>, content?: array<string, ?string>}  $translations
     * @return array{name?: array<string, ?string>, content?: array<string, ?string>}
     */
    private function sanitizeTranslations(array $translations): array
    {
        if (isset($translations['content']) && is_array($translations['content'])) {
            $translations['content'] = array_map(
                fn (?string $value): ?string => $value === null ? null : $this->sanitizeHtml($value),
                $translations['content']
            );
        }

        return $translations;
    }

    /**
     * Strip executable markup from rich-text HTML, keeping ordinary formatting
     * tags intact: whole `<script>`/`<iframe>`/`<object>`/`<embed>`/`<style>`
     * subtrees, every `on*=` event attribute, and `javascript:` /
     * `data:text/html` attribute values.
     */
    private function sanitizeHtml(string $html): string
    {
        if (trim($html) === '') {
            return $html;
        }

        if (! class_exists(\DOMDocument::class)) {
            return $this->sanitizeHtmlWithoutDom($html);
        }

        $dom = new \DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $loaded = $dom->loadHTML(
            '<?xml encoding="UTF-8">'.'<div data-sanitize-root>'.$html.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOERROR | LIBXML_NOWARNING
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded) {
            return $this->sanitizeHtmlWithoutDom($html);
        }

        $xpath = new \DOMXPath($dom);

        $forbidden = ['script', 'iframe', 'object', 'embed', 'style', 'link', 'meta', 'base', 'form'];
        foreach (iterator_to_array($xpath->query('//'.implode('|//', $forbidden))) as $node) {
            $node->parentNode?->removeChild($node);
        }

        foreach (iterator_to_array($xpath->query('//*')) as $node) {
            foreach (iterator_to_array($node->attributes) as $attribute) {
                if (str_starts_with(strtolower($attribute->nodeName), 'on') || $this->isDangerousAttributeValue((string) $attribute->nodeValue)) {
                    $node->removeAttribute($attribute->nodeName);
                }
            }
        }

        $root = $xpath->query('//div[@data-sanitize-root]')->item(0);

        if (! $root) {
            return $this->sanitizeHtmlWithoutDom($html);
        }

        $clean = '';
        foreach ($root->childNodes as $child) {
            $clean .= $dom->saveHTML($child);
        }

        return $clean;
    }

    /**
     * A URL-ish attribute value that would execute script when followed.
     */
    private function isDangerousAttributeValue(string $value): bool
    {
        $collapsed = strtolower(preg_replace('/[\s\x00-\x20]+/', '', html_entity_decode($value, ENT_QUOTES, 'UTF-8')) ?? '');

        return str_starts_with($collapsed, 'javascript:')
            || str_starts_with($collapsed, 'vbscript:')
            || str_starts_with($collapsed, 'data:text/html');
    }

    /**
     * Fallback for installs without ext-dom. Regex is not an HTML parser, so this
     * deliberately errs on the side of deleting too much: unbalanced or nested
     * forbidden tags may leave stray text behind rather than executable markup.
     * The DOMDocument path above is the one that normally runs.
     */
    private function sanitizeHtmlWithoutDom(string $html): string
    {
        $forbidden = 'script|iframe|object|embed|style|link|meta|base|form';

        $html = preg_replace('#<\s*('.$forbidden.')\b[^>]*>.*?<\s*/\s*\1\s*>#is', '', $html) ?? '';
        $html = preg_replace('#<\s*/?\s*('.$forbidden.')\b[^>]*>#is', '', $html) ?? '';
        $html = preg_replace('#\son[a-z-]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)#i', '', $html) ?? '';
        $html = preg_replace('#\s[a-z-]+\s*=\s*("\s*(?:javascript|vbscript)\s*:[^"]*"|\'\s*(?:javascript|vbscript)\s*:[^\']*\'|(?:javascript|vbscript):[^\s>]*)#i', '', $html) ?? '';
        $html = preg_replace('#\s[a-z-]+\s*=\s*("\s*data:\s*text/html[^"]*"|\'\s*data:\s*text/html[^\']*\'|data:text/html[^\s>]*)#i', '', $html) ?? '';

        return $html;
    }
}
