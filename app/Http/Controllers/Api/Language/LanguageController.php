<?php

namespace App\Http\Controllers\Api\Language;

use App\Helpers\ApiResponse;
use App\Helpers\Trans;
use App\Http\Controllers\Controller;
use App\Models\Language;
use App\Traits\PaginatesApiLists;
use Illuminate\Http\Request;

class LanguageController extends Controller
{
    use PaginatesApiLists;

    /**
     * List Languages
     *
     * Returns all active languages (with their image, if any) for populating
     * a language switcher. Public endpoint, no authentication required.
     *
     * @group Languages
     * Active languages available for the app.
     *
     * @queryParam per_page Page size. Omit it (or send `all`) to get every language in one response. Example: 20
     *
     * @response 200 scenario="Success" {"success": true, "message": "Languages retrieved successfully.", "data": [{"id": 1, "code": "en", "name": "English", "native_name": "English", "direction": "ltr", "is_default": true, "image": null}, {"id": 2, "code": "ar", "name": "Arabic", "native_name": "العربية", "direction": "rtl", "is_default": false, "image": {"id": 3, "url": "languages/ar-flag.jpg", "type": "jpg", "blurhash": "LKO2?U%2Tw=w]~RBVZRi};RPxuwH", "image_api": "http://localhost:8000/storage/languages/ar-flag.jpg"}}]}
     */
    public function index(Request $request)
    {
        $languages = $this->paginated(
            $request,
            Language::active()->with('image')->select(['id', 'code', 'name', 'native_name', 'direction', 'is_default']),
            fn (Language $language): array => [
                'id' => $language->id,
                'code' => $language->code,
                'name' => $language->name,
                'native_name' => $language->native_name,
                'direction' => $language->direction,
                'is_default' => $language->is_default,
                'image' => $language->image?->toApiArray(),
            ],
        );

        return ApiResponse::success($languages, Trans::get('api.languages'));
    }
}
