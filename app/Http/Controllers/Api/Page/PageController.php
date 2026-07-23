<?php

namespace App\Http\Controllers\Api\Page;

use App\Helpers\ApiResponse;
use App\Helpers\Trans;
use App\Http\Controllers\Controller;
use App\Models\Page;

class PageController extends Controller
{
    /**
     * List Pages
     *
     * Get all active pages.
     *
     * @group Pages
     *
     * @groupDescription Public CMS pages.
     *
     * @response 200 scenario="Success" {"success": true, "message": "Pages retrieved successfully.", "errors": null, "data": [{"id": 1, "slug": "about-us", "name": "About Us", "content": "<p>We are a company that...</p>", "image": "https://example.com/storage/pages/about-us.webp"}, {"id": 2, "slug": "privacy-policy", "name": "Privacy Policy", "content": "<p>Your privacy is important to us...</p>", "image": null}]}
     */
    public function index()
    {
        $pages = Page::active()
            ->with(['translations', 'image'])
            ->get()
            ->map(function ($page) {
                return [
                    'id' => $page->id,
                    'slug' => $page->slug,
                    'name' => $page->name_api,
                    'content' => $page->content_api,
                    'image' => $page->image?->image_api,
                ];
            });

        return ApiResponse::success($pages, Trans::get('api.pages'));
    }

    /**
     * Get Page
     *
     * Get a single page by slug.
     *
     * @group Pages
     *
     * @response 200 scenario="Success" {"success": true, "message": "Page retrieved successfully.", "errors": null, "data": {"id": 1, "slug": "about-us", "name": "About Us", "content": "<p>We are a company that...</p>", "image": "https://example.com/storage/pages/about-us.webp"}}
     * @response 404 scenario="Page not found" {"success": false, "message": "Page not found.", "errors": null, "data": null}
     */
    public function show(string $slug)
    {
        $page = Page::active()
            ->with(['translations', 'image'])
            ->where('slug', $slug)
            ->first();

        if (! $page) {
            return ApiResponse::error(Trans::get('api.page_not_found'), null, 404);
        }

        return ApiResponse::success([
            'id' => $page->id,
            'slug' => $page->slug,
            'name' => $page->name_api,
            'content' => $page->content_api,
            'image' => $page->image?->image_api,
        ], Trans::get('api.page'));
    }
}
