<?php

namespace App\Http\Controllers\Admin\Translation;

use App\Helpers\Trans;
use App\Http\Controllers\Controller;
use App\Models\Language;
use App\Models\TranslationKey;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class TranslationController extends Controller
{
    /**
     * Available translation groups.
     */
    protected array $groups = ['all', 'api', 'app', 'web'];

    public function index(Request $request)
    {
        $search = $request->input('search');
        $group = $request->input('group');
        $subGroup = $request->input('sub_group');
        $activeLanguages = Language::active()->get();
        $localeCodes = $activeLanguages->pluck('code')->toArray();

        $translations = TranslationKey::query()
            ->group($group)
            ->subGroup($subGroup)
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
                    'sub_group' => $key->sub_group,
                ];

                foreach ($localeCodes as $code) {
                    $row[$code] = $values->get($code)?->value ?? '';
                }

                return $row;
            });

        // Distinct sub-groups (app/web only, api never has one) for the filter dropdown.
        $subGroups = TranslationKey::query()
            ->whereIn('group', ['app', 'web'])
            ->where('sub_group', '!=', '')
            ->distinct()
            ->orderBy('sub_group')
            ->pluck('sub_group')
            ->toArray();

        return Inertia::render('Translation/Index', [
            'translations' => Inertia::scroll($translations),
            'languages' => $activeLanguages,
            'groups' => $this->groups,
            'subGroups' => array_merge(['all'], $subGroups),
            'filters' => [
                'search' => $search,
                'group' => $group,
                'sub_group' => $subGroup,
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

        // Detect placeholder tokens (Laravel `:name` style) in the default-language value.
        // The default language is treated as the source of truth — every other locale must
        // preserve the same placeholders so the runtime substitution still works.
        $defaultCode = Language::default()->first()?->code ?? 'en';
        $defaultValue = $request->input($defaultCode, '');
        $requiredPlaceholders = $this->extractPlaceholders($defaultValue);

        $errors = [];
        foreach ($activeLanguages as $code) {
            $value = $request->input($code, '');
            $localePlaceholders = $this->extractPlaceholders($value);

            $missing = array_diff($requiredPlaceholders, $localePlaceholders);
            if (! empty($missing)) {
                $errors[$code] = __('admin.translation_missing_placeholders', [
                    'placeholders' => implode(', ', array_map(fn ($p) => ':'.$p, $missing)),
                ]);
            }
        }

        if (! empty($errors)) {
            throw ValidationException::withMessages($errors);
        }

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

    /**
     * Extract `:placeholder` tokens from a translation value.
     *
     * @return array<int, string>
     */
    private function extractPlaceholders(string $value): array
    {
        preg_match_all('/(?<!:):([a-zA-Z_][a-zA-Z0-9_]*)/', $value, $matches);

        return array_values(array_unique($matches[1] ?? []));
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
