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
            ->paginate(10)
            ->withQueryString();

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

        $page->saveTranslations($validated['translations']);

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
            'translations' => ['required', 'array'],
            'translations.name' => ['required', 'array'],
            'translations.name.*' => ['nullable', 'string', 'max:255'],
            'translations.content' => ['nullable', 'array'],
            'translations.content.*' => ['nullable', 'string'],
        ]);

        $page->fill([
            'slug' => $validated['slug'],
            'is_active' => $request->boolean('is_active', true),
        ]);

        $page->save();
        $page->saveTranslations($validated['translations']);

        if ($request->hasFile('image')) {
            $page->saveImage($request->file('image'), 'pages');
        }

        return redirect()->back()->with('success', __('admin.updated_successfully'));
    }

    public function destroy(Page $page)
    {
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

        $pages = Page::whereIn('id', $validated['ids'])->get();
        foreach ($pages as $page) {
            $page->deleteImage();
            $page->delete();
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

        Page::whereIn('id', $validated['ids'])->update([
            'is_active' => $validated['is_active'],
        ]);

        return redirect()->back()->with('success', __('admin.updated_successfully'));
    }
}
