<?php

namespace App\Http\Controllers\Admin\AppSetting;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\Language;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class AppSettingController extends Controller
{
    public function index()
    {
        $items = AppSetting::query()
            ->with(['translations', 'image'])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        // Group into one block per type so the page renders block-by-block.
        $blocks = [];
        foreach (AppSetting::TYPES as $type) {
            $blocks[$type] = $items->where('type', $type)->values();
        }

        $languages = Language::active()->get(['id', 'code', 'name', 'native_name', 'direction']);

        return Inertia::render('AppSetting/Index', [
            'blocks' => $blocks,
            'types' => AppSetting::TYPES,
            'languages' => $languages,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validateItem($request);

        $item = AppSetting::create([
            'type' => $validated['type'],
            'url' => $validated['url'] ?? null,
            'is_active' => $request->boolean('is_active', true),
            'sort_order' => (int) (AppSetting::ofType($validated['type'])->max('sort_order') + 1),
        ]);

        $item->saveTranslations($validated['translations']);

        if ($request->hasFile('image')) {
            $item->saveImage($request->file('image'), 'app-settings');
        }

        return redirect()->back()->with('success', __('admin.created_successfully'));
    }

    public function update(Request $request, AppSetting $appSetting)
    {
        $validated = $this->validateItem($request, $appSetting);

        $appSetting->fill([
            'type' => $validated['type'],
            'url' => $validated['url'] ?? null,
            'is_active' => $request->boolean('is_active', true),
        ])->save();

        $appSetting->saveTranslations($validated['translations']);

        if ($request->hasFile('image')) {
            $appSetting->saveImage($request->file('image'), 'app-settings');
        } elseif ($request->boolean('remove_image')) {
            $appSetting->deleteImage();
        }

        return redirect()->back()->with('success', __('admin.updated_successfully'));
    }

    public function destroy(AppSetting $appSetting)
    {
        $appSetting->deleteImage();
        $appSetting->delete();

        return redirect()->back()->with('success', __('admin.deleted_successfully'));
    }

    public function bulkUpdate(Request $request)
    {
        $validated = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['exists:app_settings,id'],
            'is_active' => ['required', 'boolean'],
        ]);

        AppSetting::whereIn('id', $validated['ids'])->update(['is_active' => $validated['is_active']]);

        return redirect()->back()->with('success', __('admin.updated_successfully'));
    }

    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['exists:app_settings,id'],
        ]);

        foreach (AppSetting::whereIn('id', $validated['ids'])->get() as $item) {
            $item->deleteImage();
            $item->delete();
        }

        return redirect()->back()->with('success', __('admin.deleted_successfully'));
    }

    private function validateItem(Request $request, ?AppSetting $appSetting = null): array
    {
        return $request->validate([
            'type' => ['required', Rule::in(AppSetting::TYPES)],
            'url' => ['nullable', 'string', 'max:2048'],
            'is_active' => ['boolean'],
            'image' => ['nullable', 'image', 'max:2048'],
            'remove_image' => ['nullable', 'boolean'],
            'translations' => ['required', 'array'],
            'translations.text' => ['required', 'array'],
            'translations.text.*' => ['nullable', 'string', 'max:500'],
        ]);
    }
}
