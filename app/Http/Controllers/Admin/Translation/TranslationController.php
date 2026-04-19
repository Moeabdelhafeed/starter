<?php

namespace App\Http\Controllers\Admin\Translation;

use App\Helpers\Trans;
use App\Http\Controllers\Controller;
use App\Models\Language;
use App\Models\TranslationKey;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TranslationController extends Controller
{
    /**
     * Available translation groups.
     */
    protected array $groups = ['all', 'api', 'app'];

    public function index(Request $request)
    {
        $search = $request->input('search');
        $group = $request->input('group');
        $activeLanguages = Language::active()->get();
        $localeCodes = $activeLanguages->pluck('code')->toArray();

        $translations = TranslationKey::query()
            ->group($group)
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('key', 'like', "%{$search}%")
                        ->orWhereHas('values', function ($q) use ($search) {
                            $q->where('value', 'like', "%{$search}%");
                        });
                });
            })
            ->with([
                'values' => function ($query) use ($localeCodes) {
                    $query->whereIn('locale', $localeCodes);
                },
            ])
            ->latest()
            ->paginate(10)
            ->withQueryString()
            ->through(function ($key) use ($localeCodes) {
                $values = $key->values->keyBy('locale');

                $row = [
                    'id' => $key->id,
                    'key' => $key->key,
                    'group' => $key->group,
                ];

                foreach ($localeCodes as $code) {
                    $row[$code] = $values->get($code)?->value ?? '';
                }

                return $row;
            });

        return Inertia::render('Translation/Index', [
            'translations' => Inertia::scroll($translations),
            'languages' => $activeLanguages,
            'groups' => $this->groups,
            'filters' => [
                'search' => $search,
                'group' => $group,
            ],
        ]);
    }

    public function edit(Request $request)
    {
        $activeLanguages = Language::active()->pluck('code')->toArray();

        $rules = ['id' => 'required|exists:translation_keys,id'];
        foreach ($activeLanguages as $code) {
            $rules[$code] = 'required|string';
        }

        $request->validate($rules);

        $translation = TranslationKey::findOrFail($request->id);

        foreach ($activeLanguages as $code) {
            $translation->values()->updateOrCreate(
                ['locale' => $code],
                ['value' => $request->input($code)]
            );
        }

        // Clear the translation cache
        Trans::clearCache($translation->group, $translation->key);

        return redirect()->back()->with([
            'success' => __('admin.updated_successfully'),
        ]);
    }

    public function destroy(TranslationKey $translation)
    {
        // Delete all associated values
        $translation->values()->delete();

        // Clear cache before deleting
        Trans::clearCache($translation->group, $translation->key);

        $translation->delete();

        return redirect()->back()->with('success', __('admin.deleted_successfully'));
    }

    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['required', 'integer', 'exists:translation_keys,id'],
        ]);

        $translations = TranslationKey::whereIn('id', $validated['ids'])->get();

        foreach ($translations as $translation) {
            $translation->values()->delete();
            Trans::clearCache($translation->group, $translation->key);
            $translation->delete();
        }

        return redirect()->back()->with('success', __('admin.deleted_successfully'));
    }
}
