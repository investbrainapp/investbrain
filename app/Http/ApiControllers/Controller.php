<?php

declare(strict_types=1);

namespace App\Http\ApiControllers;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

abstract class Controller
{
    public const DEFAULT_ITEMS_PER_PAGE = 15;

    public const MAX_ITEMS_PER_PAGE = 100;

    /**
     * Validate the shared pagination parameters and return the requested page size.
     */
    protected function itemsPerPage(Request $request): int
    {
        $validated = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'itemsPerPage' => ['sometimes', 'integer', 'min:1', 'max:'.self::MAX_ITEMS_PER_PAGE],
        ]);

        return (int) ($validated['itemsPerPage'] ?? self::DEFAULT_ITEMS_PER_PAGE);
    }

    /**
     * Returns a query callback that case-insensitively matches the `search` query parameter
     * against any of the given columns. It does nothing when no search term is given.
     *
     * @param  array<int, string>  $columns
     */
    protected function search(Request $request, array $columns): Closure
    {
        $term = $request->validate([
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
        ])['search'] ?? null;

        return function (Builder $query) use ($term, $columns) {
            if (blank($term)) {
                return;
            }

            $query->where(function (Builder $query) use ($term, $columns) {
                foreach ($columns as $column) {
                    $query->orWhereLike($query->qualifyColumn($column), '%'.$term.'%');
                }
            });
        };
    }
}
