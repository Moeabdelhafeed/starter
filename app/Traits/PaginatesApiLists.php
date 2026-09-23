<?php

namespace App\Traits;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * The project's opt-in pagination convention for API list endpoints.
 *
 * A client that asks for nothing gets everything — mobile clients generally
 * want the whole (small) list in one call. Paging is opted into per request via
 * `per_page`; the literal string `all` is also treated as "everything", so a
 * client can pass the parameter unconditionally.
 *
 * Passing the paginator straight to ApiResponse::success() nests Laravel's
 * standard paginator shape under `data`, so no extra wiring is needed.
 */
trait PaginatesApiLists
{
    /**
     * @template TValue
     *
     * @param  Builder<*>  $query
     * @param  callable(mixed): TValue  $present
     * @return LengthAwarePaginator<int, TValue>|Collection<int, TValue>
     */
    protected function paginated(Request $request, Builder $query, callable $present): LengthAwarePaginator|Collection
    {
        $perPage = $request->input('per_page');

        if ($perPage === null || $perPage === 'all') {
            return $query->get()->map($present);
        }

        return $query->paginate(max(1, (int) $perPage))->through($present);
    }
}
