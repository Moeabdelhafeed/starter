<?php

namespace App\Http\Controllers\Api\Translations;

use App\Helpers\ApiResponse;
use App\Helpers\Trans;
use App\Http\Controllers\Controller;
use App\Models\Language;
use App\Models\TranslationKey;
use App\Models\TranslationValue;
use Illuminate\Http\Request;

class TranslationController extends Controller
{
    private const ALLOWED_GROUPS = ['app', 'web'];

    public function index(Request $request)
    {
        $request->validate([
            'group' => ['nullable', 'string', 'in:'.implode(',', self::ALLOWED_GROUPS)],
        ]);

        $group = $request->input('group', 'app');
        $locale = app()->getLocale();

        $translations = TranslationKey::where('group', $group)
            ->with(['values' => fn ($q) => $q->where('locale', $locale)])
            ->get()
            ->mapWithKeys(function ($key) {
                $value = $key->values->first()?->value ?? '';

                return [$key->key => $value];
            })
            ->toArray();

        return ApiResponse::success([
            'group' => $group,
            'locale' => $locale,
            'translations' => $translations,
        ], Trans::get('api.translations'));
    }

    /**
     * Store translations from a flat JSON body keyed by `Accept-Language`.
     *
     * Body shape:
     * {
     *     "welcome_screen_title": "Welcome",
     *     "tap_to_continue": "Tap to continue"
     * }
     *
     * Behavior:
     * - The locale comes from the request's `Accept-Language` header (resolved by SetLocaleMiddleware).
     * - For each key:
     *   - First time seen → key is created in the `app` group; the value is saved for the
     *     header's locale; all other active locales are stored as null (placeholder rows).
     *   - Already exists → only the header's locale value is updated. Other locales are untouched.
     * - `api`-group keys are server-controlled and cannot be created or modified here.
     */
    public function store(Request $request)
    {
        $request->validate([
            'group' => ['nullable', 'string', 'in:'.implode(',', self::ALLOWED_GROUPS)],
            'translations' => ['required', 'array'],
        ]);

        $group = $request->input('group', 'app');
        $translations = $request->input('translations', []);

        if (empty($translations)) {
            return ApiResponse::error(Trans::get('api.translations_empty'), null, 400);
        }

        $locale = app()->getLocale();
        $activeCodes = Language::active()->pluck('code')->toArray();

        if (! in_array($locale, $activeCodes, true)) {
            return ApiResponse::error(Trans::get('api.translations_empty'), null, 400);
        }

        $createdCount = 0;
        $updatedCount = 0;
        $errors = [];

        foreach ($translations as $keyName => $value) {
            if (! is_string($keyName) || $keyName === '') {
                continue;
            }

            if (! is_string($value) && ! is_null($value)) {
                $errors[] = "Key '{$keyName}' must be a string value";

                continue;
            }

            $translationKey = TranslationKey::where('key', $keyName)
                ->where('group', $group)
                ->first();

            if (! $translationKey) {
                $translationKey = TranslationKey::create([
                    'key' => $keyName,
                    'group' => $group,
                ]);

                // Seed placeholder rows for every active locale, then set the header locale's value.
                foreach ($activeCodes as $code) {
                    TranslationValue::create([
                        'translation_key_id' => $translationKey->id,
                        'locale' => $code,
                        'value' => $code === $locale ? $value : null,
                    ]);
                }

                $createdCount++;
            } else {
                // Existing key — only update the header's locale.
                TranslationValue::updateOrCreate(
                    ['translation_key_id' => $translationKey->id, 'locale' => $locale],
                    ['value' => $value]
                );

                $updatedCount++;
            }
        }

        Trans::clearCache();

        $response = [
            'group' => $group,
            'locale' => $locale,
            'created' => $createdCount,
            'updated' => $updatedCount,
        ];

        if (! empty($errors)) {
            $response['errors'] = $errors;
        }

        return ApiResponse::success($response, Trans::get('api.translations_added'));
    }
}
