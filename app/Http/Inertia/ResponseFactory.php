<?php

namespace App\Http\Inertia;

use Inertia\ProvidesScrollMetadata;
use Inertia\ResponseFactory as BaseResponseFactory;

/**
 * Makes `Inertia::scroll()` return this project's ScrollProp.
 *
 * Bound over the package's factory in AppServiceProvider::register(), rather than asking
 * every controller to reach for a different helper: a list added next year gets the
 * corrected behaviour by writing the same `Inertia::scroll($paginator)` every other list
 * already writes, and there is no second spelling to know about.
 */
final class ResponseFactory extends BaseResponseFactory
{
    public function scroll($value, string $wrapper = 'data', ProvidesScrollMetadata|callable|null $metadata = null): ScrollProp
    {
        return new ScrollProp($value, $wrapper, $metadata);
    }
}
