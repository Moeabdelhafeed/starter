<?php

namespace App\Http\Controllers\Page;

use App\Http\Controllers\Controller;
use App\Models\Page;
use Inertia\Inertia;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PageController extends Controller
{
    /**
     * Render an active page publicly. Translation auto-resolves to the
     * current locale via `name_api` / `content_api` accessors.
     */
    public function show(string $slug)
    {
        $page = Page::active()
            ->with(['translations', 'image'])
            ->where('slug', $slug)
            ->first();

        if (! $page) {
            throw new NotFoundHttpException;
        }

        return Inertia::render('Page/Show', [
            'page' => [
                'id' => $page->id,
                'slug' => $page->slug,
                'name' => $page->name_api,
                'content' => $page->content_api,
                'image' => $page->image?->image_api,
            ],
        ]);
    }
}
