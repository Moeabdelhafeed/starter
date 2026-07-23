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

    /**
     * List Translations
     *
     * Public endpoint. Returns every translation key in the given `group` for the
     * current locale (resolved server-side, e.g. via `Accept-Language`), nested by
     * `sub_group`. Keys stored with an empty sub_group fold under `"general"`.
     *
     * @group Translations
     *
     * @groupDescription CRUD for app/web-facing translation strings, organized by group + sub_group.
     *
     * @response 200 scenario="Success (group=web)" {"success": true, "message": "Translations", "errors": null, "data": {"group": "web", "locale": "en", "translations": {"general": {"welcome": "Welcome"}, "auth": {"login_title": "Login", "login_description": "Enter your :field below to sign in."}}}}
     * @response 422 scenario="Invalid group" {"success": false, "message": "The selected group is invalid.", "errors": {"group": ["The selected group is invalid."]}, "data": null}
     */
    public function index(Request $request)
    {
        $request->validate([
            'group' => ['nullable', 'string', 'in:'.implode(',', self::ALLOWED_GROUPS)],
        ]);

        $group = $request->input('group', 'app');
        $locale = app()->getLocale();

        // app/web translations are organized under sub-groups. Return the whole group
        // nested by sub_group so the client fetches every section in one call.
        // Keys with no sub_group (legacy/empty sentinel) fall under 'general'.
        $translations = [];

        TranslationKey::where('group', $group)
            ->with(['values' => fn ($q) => $q->where('locale', $locale)])
            ->get()
            ->each(function ($key) use (&$translations) {
                $subGroup = $key->sub_group !== '' ? $key->sub_group : 'general';
                $translations[$subGroup][$key->key] = $key->values->first()?->value ?? '';
            });

        return ApiResponse::success([
            'group' => $group,
            'locale' => $locale,
            'translations' => $translations,
        ], Trans::get('api.translations'));
    }

    /**
     * Add or Update Translations
     *
     * Store translations from a flat JSON body keyed by `Accept-Language`.
     *
     * Body shape:
     * {
     *     "group": "web",
     *     "sub_group": "auth",
     *     "translations": {
     *         "welcome_screen_title": "Welcome",
     *         "tap_to_continue": "Tap to continue"
     *     }
     * }
     *
     * Behavior:
     * - The locale comes from the request's `Accept-Language` header (resolved by SetLocaleMiddleware).
     * - `sub_group` is required (free-form slug) — app/web keys always live under a sub-group.
     * - For each key:
     *   - First time seen → key is created in the group + sub_group; the value is saved for the
     *     header's locale; all other active locales are stored as null (placeholder rows).
     *   - Already exists (same key + group + sub_group) → only the header's locale value is
     *     updated. Other locales are untouched.
     * - `api`-group keys are server-controlled and cannot be created or modified here.
     *
     * @group Translations
     *
     * @response 200 scenario="Created new key" {"success": true, "message": "Translations added successfully.", "errors": null, "data": {"group": "web", "sub_group": "auth", "locale": "en", "created": 1, "updated": 0}}
     * @response 200 scenario="Updated existing key" {"success": true, "message": "Translations added successfully.", "errors": null, "data": {"group": "web", "sub_group": "auth", "locale": "en", "created": 0, "updated": 1}}
     * @response 422 scenario="Missing sub_group" {"success": false, "message": "The sub group field is required.", "errors": {"sub_group": ["The sub group field is required."]}, "data": null}
     * @response 422 scenario="Invalid sub_group format" {"success": false, "message": "The sub group field format is invalid.", "errors": {"sub_group": ["The sub group field format is invalid."]}, "data": null}
     * @response 422 scenario="Invalid group" {"success": false, "message": "The selected group is invalid.", "errors": {"group": ["The selected group is invalid."]}, "data": null}
     * @response 422 scenario="Missing translations" {"success": false, "message": "The translations field is required.", "errors": {"translations": ["The translations field is required."]}, "data": null}
     * @response 400 scenario="Resolved locale is not an active language (server config edge case)" {"success": false, "message": "No translations provided.", "errors": null, "data": null}
     */
    public function store(Request $request)
    {
        $request->validate([
            'group' => ['nullable', 'string', 'in:'.implode(',', self::ALLOWED_GROUPS)],
            'sub_group' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9]([a-z0-9_-]*[a-z0-9])?$/'],
            'translations' => ['required', 'array'],
        ]);

        $group = $request->input('group', 'app');
        $subGroup = $request->input('sub_group');
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
                ->where('sub_group', $subGroup)
                ->first();

            if (! $translationKey) {
                $translationKey = TranslationKey::create([
                    'key' => $keyName,
                    'group' => $group,
                    'sub_group' => $subGroup,
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
            'sub_group' => $subGroup,
            'locale' => $locale,
            'created' => $createdCount,
            'updated' => $updatedCount,
        ];

        if (! empty($errors)) {
            $response['errors'] = $errors;
        }

        return ApiResponse::success($response, Trans::get('api.translations_added'));
    }

    /**
     * Delete Translation Key
     *
     * Delete a single translation key (and all of its locale values, cascade-deleted) at
     * (key, group, sub_group). `api`-group keys are server-controlled and cannot be deleted here
     * (same restriction as store — only `app`/`web` are accepted).
     *
     * @group Translations
     *
     * @response 200 scenario="Success" {"success": true, "message": "Translation key deleted successfully.", "errors": null, "data": null}
     * @response 404 scenario="No key at that (key, group, sub_group)" {"success": false, "message": "Translation key not found.", "errors": null, "data": null}
     * @response 422 scenario="Missing sub_group/key" {"success": false, "message": "The sub group field is required. (and 1 more error)", "errors": {"sub_group": ["The sub group field is required."], "key": ["The key field is required."]}, "data": null}
     */
    public function destroy(Request $request)
    {
        $request->validate([
            'group' => ['nullable', 'string', 'in:'.implode(',', self::ALLOWED_GROUPS)],
            'sub_group' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9]([a-z0-9_-]*[a-z0-9])?$/'],
            'key' => ['required', 'string', 'max:255'],
        ]);

        $group = $request->input('group', 'app');
        $subGroup = $request->input('sub_group');
        $key = $request->input('key');

        $translationKey = TranslationKey::where('key', $key)
            ->where('group', $group)
            ->where('sub_group', $subGroup)
            ->first();

        if (! $translationKey) {
            return ApiResponse::error(Trans::get('api.translation_key_not_found'), null, 404);
        }

        $translationKey->delete(); // cascade-deletes translation_values

        Trans::clearCache();

        return ApiResponse::success(null, Trans::get('api.translation_key_deleted'));
    }
}
