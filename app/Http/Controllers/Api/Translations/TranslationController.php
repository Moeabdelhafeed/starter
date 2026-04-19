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
    public function index()
    {
        $locale = app()->getLocale();

        $translations = TranslationKey::where('group', 'app')
            ->with(['values' => fn ($q) => $q->where('locale', $locale)])
            ->get()
            ->mapWithKeys(function ($key) {
                $value = $key->values->first()?->value ?? '';

                return [$key->key => $value];
            })
            ->toArray();

        return ApiResponse::success($translations, Trans::get('api.translations'));
    }

    /**
     * Store translations from JSON body.
     *
     * Format:
     * {
     *     "key_name": {
     *         "default": "Value for all languages"
     *     },
     *     "another_key": {
     *         "en": "English value",
     *         "ar": "Arabic value"
     *     }
     * }
     *
     * Rules:
     * - If "default" is present, value is applied to ALL active languages
     * - If specific language codes are present, only those languages are updated
     * - Cannot mix "default" with specific language codes
     */
    public function store(Request $request)
    {
        $activeCodes = Language::active()->pluck('code')->toArray();
        $translations = $request->all();

        if (empty($translations)) {
            return ApiResponse::error(Trans::get('api.translations_empty'), null, 400);
        }

        $createdCount = 0;
        $updatedCount = 0;
        $errors = [];

        foreach ($translations as $keyName => $values) {
            if (! is_array($values)) {
                $errors[] = "Key '{$keyName}' must have an object value";

                continue;
            }

            $hasDefault = array_key_exists('default', $values);
            $languageCodes = array_filter(array_keys($values), fn ($k) => $k !== 'default');
            $hasLanguages = count($languageCodes) > 0;

            // Validate: cannot mix default with specific languages
            if ($hasDefault && $hasLanguages) {
                $errors[] = "Key '{$keyName}' cannot have both 'default' and specific language codes";

                continue;
            }

            // Validate: must have either default or language codes
            if (! $hasDefault && ! $hasLanguages) {
                $errors[] = "Key '{$keyName}' must have either 'default' or language codes";

                continue;
            }

            // Create or get the translation key (always in 'app' group)
            $translationKey = TranslationKey::where('key', $keyName)
                ->where('group', 'app')
                ->first();

            $isNew = ! $translationKey;

            if (! $translationKey) {
                $translationKey = TranslationKey::create([
                    'key' => $keyName,
                    'group' => 'app',
                ]);
            }

            if ($hasDefault) {
                // Apply default value to ALL active languages
                $defaultValue = $values['default'];
                foreach ($activeCodes as $code) {
                    TranslationValue::updateOrCreate(
                        ['translation_key_id' => $translationKey->id, 'locale' => $code],
                        ['value' => $defaultValue]
                    );
                }
            } else {
                // Apply specific language values
                foreach ($languageCodes as $code) {
                    if (in_array($code, $activeCodes)) {
                        TranslationValue::updateOrCreate(
                            ['translation_key_id' => $translationKey->id, 'locale' => $code],
                            ['value' => $values[$code]]
                        );
                    }
                }
            }

            if ($isNew) {
                $createdCount++;
            } else {
                $updatedCount++;
            }
        }

        // Clear translation cache
        Trans::clearCache();

        $response = [
            'created' => $createdCount,
            'updated' => $updatedCount,
        ];

        if (! empty($errors)) {
            $response['errors'] = $errors;
        }

        return ApiResponse::success($response, Trans::get('api.translations_added'));
    }
}
