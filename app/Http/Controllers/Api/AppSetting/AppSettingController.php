<?php

namespace App\Http\Controllers\Api\AppSetting;

use App\Helpers\ApiResponse;
use App\Helpers\Trans;
use App\Http\Controllers\Controller;
use App\Models\AppSetting;

class AppSettingController extends Controller
{
    /**
     * List App Settings
     *
     * All active app settings, grouped by block type and localized to the
     * request's Accept-Language.
     *
     * Public endpoint, no authentication or parameters required. Returns every
     * active AppSetting row, translated via `text_api` and grouped under each
     * of the fixed block types (`social`, `contact`, `app_store`, `google_play`,
     * `app_gallery`). Types with no active rows are returned as empty arrays.
     *
     * @group App Settings
     *
     * @groupDescription Public app configuration blocks (social links, contact info, store links).
     *
     * @response 200 scenario="Success" {"success": true, "message": "App settings retrieved successfully.", "errors": null, "data": {"social": [{"id": 1, "text": "Follow us on Instagram", "url": "https://instagram.com/ourapp", "image": "http://localhost:8000/storage/app-settings/instagram-icon.webp"}], "contact": [{"id": 2, "text": "support@ourapp.com", "url": "mailto:support@ourapp.com", "image": null}], "app_store": [{"id": 3, "text": "Download on the App Store", "url": "https://apps.apple.com/app/id123456789", "image": "http://localhost:8000/storage/app-settings/app-store-badge.webp"}], "google_play": [], "app_gallery": []}}
     */
    public function index()
    {
        $items = AppSetting::active()
            ->with(['translations', 'image'])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $grouped = [];
        foreach (AppSetting::TYPES as $type) {
            $grouped[$type] = $items
                ->where('type', $type)
                ->map(fn ($item) => [
                    'id' => $item->id,
                    'text' => $item->text_api,
                    'url' => $item->url,
                    'image' => $item->image?->image_api,
                ])
                ->values();
        }

        return ApiResponse::success($grouped, Trans::get('api.app_settings'));
    }
}
