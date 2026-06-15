<?php

namespace App\Http\Controllers\Api\Page;

use App\Helpers\ApiResponse;
use App\Helpers\Trans;
use App\Http\Controllers\Controller;
use App\Models\Page;

class PageController extends Controller
{
    /**
     * Get all active pages.
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
     * Get a single page by slug.
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
