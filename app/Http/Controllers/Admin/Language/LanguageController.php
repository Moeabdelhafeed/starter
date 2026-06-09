<?php

namespace App\Http\Controllers\Admin\Language;

use App\Http\Controllers\Controller;
use App\Models\Language;
use App\Models\TranslationKey;
use App\Models\TranslationValue;
use Database\Seeders\TranslationSeeder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use LaravelLang\LocaleList\Locale;
use LaravelLang\Locales\Facades\Locales;
use LaravelLang\NativeLocaleNames\LocaleNames;

class LanguageController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');
        $isActive = $request->input('is_active');

        $languages = Language::query()
            ->with('image')
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('native_name', 'like', "%{$search}%");
                });
            })
            ->when($isActive !== null && $isActive !== 'all', function ($query) use ($isActive) {
                $query->where('is_active', $isActive);
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Language/Index', [
            'languages' => Inertia::scroll($languages),
            'filters' => [
                'search' => $search,
                'is_active' => $isActive,
            ],
            'availableLocales' => $this->getAvailableLocales(),
        ]);
    }

    /**
     * Get list of available locales with their metadata.
     * Protected languages (en, ar) are always available if not in database.
     */
    protected function getAvailableLocales(): array
    {
        $localeNames = new LocaleNames;
        $englishNames = $localeNames->get(Locale::English);
        $nativeNames = $localeNames->get(null); // Native names

        // Get already added language codes
        $existingCodes = Language::pluck('code')->toArray();

        $locales = [];
        $addedCodes = [];

        // First, add protected languages if not in database
        $protectedLocales = [
            'en' => ['name' => 'English', 'native_name' => 'English', 'direction' => 'ltr'],
            'ar' => ['name' => 'Arabic', 'native_name' => 'العربية', 'direction' => 'rtl'],
        ];

        foreach ($protectedLocales as $code => $data) {
            if (! in_array($code, $existingCodes)) {
                $locales[] = [
                    'code' => $code,
                    'name' => $data['name'],
                    'native_name' => $data['native_name'],
                    'direction' => $data['direction'],
                ];
                $addedCodes[] = $code;
            }
        }

        // Then add the rest from laravel-lang package
        foreach (Locale::cases() as $locale) {
            $code = $locale->value;

            // Skip already added languages (from database or protected)
            if (in_array($code, $existingCodes) || in_array($code, $addedCodes)) {
                continue;
            }

            // Get direction from Locales facade
            try {
                $info = Locales::info($code);
                $direction = $info->direction->value ?? 'ltr';
            } catch (\Exception $e) {
                $direction = 'ltr';
            }

            $locales[] = [
                'code' => $code,
                'name' => $englishNames[$code] ?? $code,
                'native_name' => $nativeNames[$code] ?? $code,
                'direction' => $direction,
            ];
        }

        // Sort by English name
        usort($locales, fn ($a, $b) => strcmp($a['name'], $b['name']));

        return $locales;
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:10', 'unique:languages,code'],
            'name' => ['required', 'string', 'max:255'],
            'native_name' => ['required', 'string', 'max:255'],
            'direction' => ['required', Rule::in(['ltr', 'rtl'])],
            'image' => ['nullable', 'image', 'max:2048'],
            'is_active' => ['boolean'],
            'is_default' => ['boolean'],
        ]);

        if ($request->boolean('is_default')) {
            Language::where('is_default', true)->update(['is_default' => false]);
        }

        $language = Language::create([
            'code' => $validated['code'],
            'name' => $validated['name'],
            'native_name' => $validated['native_name'],
            'direction' => $validated['direction'],
            'is_active' => $request->boolean('is_active', true),
            'is_default' => $request->boolean('is_default', false),
        ]);

        if ($request->hasFile('image')) {
            $language->saveImage($request->file('image'), 'languages');
        }

        // Install translation files for the new language if supported
        $this->installLanguageTranslations($validated['code']);

        // Seed database translations for the new language (API group)
        TranslationSeeder::seedForLanguage($validated['code']);

        // Copy app group translations from default language
        $this->copyAppTranslationsFromDefault($validated['code']);

        return redirect()->back()->with('success', __('admin.created_successfully'));
    }

    public function update(Request $request, Language $language)
    {
        // Note: 'code' is intentionally not editable as it's used for translation file paths
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'native_name' => ['required', 'string', 'max:255'],
            'direction' => ['required', Rule::in(['ltr', 'rtl'])],
            'image' => ['nullable', 'image', 'max:2048'],
            'remove_image' => ['nullable', 'boolean'],
            'is_active' => ['boolean'],
            'is_default' => ['boolean'],
        ]);

        if ($language->is_default && ! $request->boolean('is_active', true)) {
            return redirect()->back()->with('error', __('admin.cannot_deactivate_default_language'));
        }

        if ($language->is_default && ! $request->boolean('is_default', true)) {
            return redirect()->back()->with('error', __('admin.cannot_unset_default_language'));
        }

        if ($request->boolean('is_default') && ! $language->is_default) {
            Language::where('is_default', true)->update(['is_default' => false]);
        }

        if ($request->hasFile('image')) {
            $language->saveImage($request->file('image'), 'languages');
        } elseif ($request->boolean('remove_image')) {
            $language->deleteImage();
        }

        $language->fill([
            'name' => $validated['name'],
            'native_name' => $validated['native_name'],
            'direction' => $validated['direction'],
            'is_active' => $request->boolean('is_active', true),
            'is_default' => $request->boolean('is_default', $language->is_default),
        ]);

        $language->save();

        return redirect()->back()->with('success', __('admin.updated_successfully'));
    }

    public function destroy(Language $language)
    {
        if ($language->is_default) {
            return redirect()->back()->with('error', __('admin.cannot_delete_default_language'));
        }

        if (Language::active()->count() <= 1) {
            return redirect()->back()->with('error', __('admin.cannot_delete_last_language'));
        }

        try {
            $language->deleteImage();
        } catch (\Exception $e) {
            report($e);
        }

        $this->removeLanguageTranslations($language->code);

        if (! in_array($language->code, $this->protectedLanguages)) {
            try {
                $language->translationValues()->delete();
            } catch (\Exception $e) {
                report($e);
            }
        }

        $language->delete();

        return redirect()->back()->with('success', __('admin.deleted_successfully'));
    }

    /**
     * Check if a locale code is supported by laravel-lang.
     */
    protected function isLocaleSupported(string $code): bool
    {
        return Locale::tryFrom($code) !== null;
    }

    /**
     * Protected language folders that have custom translations.
     * These should not be overwritten by laravel-lang package.
     */
    protected array $protectedLanguages = ['en', 'ar'];

    /**
     * Install translation files for a language code.
     */
    protected function installLanguageTranslations(string $code): void
    {
        // Skip laravel-lang installation for protected languages (they have custom translations)
        $isProtected = in_array($code, $this->protectedLanguages);

        // Install laravel-lang translations if locale is supported and not protected
        if (! $isProtected && $this->isLocaleSupported($code)) {
            try {
                Artisan::call('lang:add', [
                    'locales' => [$code],
                ]);
            } catch (\Exception $e) {
                report($e);
            }
        }

        try {
            // Ensure the language directory exists
            $langPath = lang_path($code);
            if (! File::isDirectory($langPath)) {
                File::makeDirectory($langPath, 0755, true);
            }

            // Copy custom translation files from English as a starting point (skip for protected)
            if (! $isProtected) {
                $this->copyCustomTranslationFiles($code);
            }
        } catch (\Exception $e) {
            report($e);
        }
    }

    /**
     * Copy custom translation files from English to the new language.
     * Note: api.php is not copied because API translations are database-driven via Trans helper.
     */
    protected function copyCustomTranslationFiles(string $code): void
    {
        // Skip English as it's the source language
        if ($code === 'en') {
            return;
        }

        // Custom files to copy (api.php excluded - API translations are in database)
        $customFiles = ['admin.php'];

        foreach ($customFiles as $file) {
            $sourcePath = lang_path("en/{$file}");
            $targetPath = lang_path("{$code}/{$file}");

            // Copy if source exists and target doesn't
            if (File::exists($sourcePath) && ! File::exists($targetPath)) {
                File::copy($sourcePath, $targetPath);
            }
        }
    }

    /**
     * Remove translation files for a language code.
     * Protected folders (en, ar) are never deleted as they contain core translations.
     */
    protected function removeLanguageTranslations(string $code): void
    {
        // Don't delete protected language folders
        if (in_array($code, $this->protectedLanguages)) {
            return;
        }

        try {
            $langPath = lang_path($code);

            if (File::isDirectory($langPath)) {
                File::deleteDirectory($langPath);
            }

            // Also remove JSON translation file if exists
            $jsonPath = lang_path("{$code}.json");
            if (File::exists($jsonPath)) {
                File::delete($jsonPath);
            }
        } catch (\Exception $e) {
            // Log the error but don't fail the language deletion
            report($e);
        }
    }

    /**
     * Copy app group translations from the default language to a new language.
     */
    protected function copyAppTranslationsFromDefault(string $code): void
    {
        $defaultLanguage = Language::getDefault();
        if (! $defaultLanguage) {
            return;
        }

        // Get all app group translation keys with their values for the default language
        $appKeys = TranslationKey::where('group', 'app')
            ->with(['values' => fn ($q) => $q->where('locale', $defaultLanguage->code)])
            ->get();

        foreach ($appKeys as $key) {
            $defaultValue = $key->values->first()?->value;

            if ($defaultValue) {
                TranslationValue::firstOrCreate(
                    [
                        'translation_key_id' => $key->id,
                        'locale' => $code,
                    ],
                    [
                        'value' => $defaultValue,
                    ]
                );
            }
        }
    }
}
