<?php

namespace App\Http\Inertia;

use Illuminate\Http\Request;
use Inertia\ScrollProp as BaseScrollProp;
use Inertia\Support\Header;

/**
 * An `Inertia::scroll()` prop that merges only for the scroll component's own fetch.
 *
 * The package's own prop appends **whenever the merge-intent header is absent**
 * (`ScrollProp::configureMergeIntent()`), which is every request that is not the
 * InfiniteScroll component asking for the next page — an initial load, a filter, and,
 * the damaging one, the redirect after a write. That redirect carries
 * `X-Inertia-Scroll-Restore`, so `scrollPaginate()` answers it with pages 1..N; told to
 * append, the client stacked those onto the pages 1..N it already had and every row
 * appeared twice.
 *
 * The escape hatch was naming the prop in the visit's `reset:` array, which makes the
 * resolver skip the merge metadata. That is one array in one options object per call
 * site, on nine lists, remembered forever — and two lists never had it. Whether a
 * response should merge is a fact about the request, not about whoever wrote the call
 * site, so it is decided here instead.
 *
 * `reset:` is still worth passing: it also resyncs the client's page counter
 * (`scrollProps.<name>.reset`), which matters when a bulk delete shortens the list
 * enough to change where the next page begins.
 */
final class ScrollProp extends BaseScrollProp
{
    public function configureMergeIntent(?Request $request = null): static
    {
        $request ??= request();

        // Only the InfiniteScroll component sends this, and it sends it on every page
        // fetch. Anything else is looking at a fresh list and must replace what it has.
        if (! $request->hasHeader(Header::INFINITE_SCROLL_MERGE_INTENT)) {
            $this->merge = false;

            return $this;
        }

        return parent::configureMergeIntent($request);
    }
}
