<?php

namespace App\Http\Controllers\Api\Language;

use App\Helpers\ApiResponse;
use App\Helpers\Trans;
use App\Http\Controllers\Controller;
use App\Models\Language;

class LanguageController extends Controller
{
    /**
     * List Languages
     *
     * Returns all active languages (with their image, if any) for populating
     * a language switcher. Public endpoint, no authentication required.
     *
     * @group Languages
     *
     * @groupDescription Active languages available for the app.
     *
     * @response 200 scenario="Success" {"success": true, "message": "Languages retrieved successfully.", "data": [{"id": 1, "code": "en", "name": "English", "native_name": "English", "direction": "ltr", "is_default": true, "image": null}, {"id": 2, "code": "ar", "name": "Arabic", "native_name": "العربية", "direction": "rtl", "is_default": false, "image": {"id": 3, "url": "languages/ar-flag.jpg", "type": "jpg", "blurhash": "LKO2?U%2Tw=w]~RBVZRi};RPxuwH", "imageable_id": 2, "imageable_type": "App\\Models\\Language", "created_at": "2026-07-01T10:00:00.000000Z", "updated_at": "2026-07-01T10:00:00.000000Z", "image_api": "http://localhost:8000/storage/languages/ar-flag.jpg"}}]}
     */
    public function index()
    {
        $languages = Language::active()
            ->with('image')
            ->get(['id', 'code', 'name', 'native_name', 'direction', 'is_default']);

        return ApiResponse::success($languages, Trans::get('api.languages'));
    }
}
