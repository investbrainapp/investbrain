<?php

declare(strict_types=1);

namespace App\Http\ApiControllers;

use App\Actions\Holding\UpdateHolding;
use App\Http\ApiControllers\Controller as ApiController;
use App\Http\Resources\HoldingResource;
use App\Models\Holding;
use App\Models\Portfolio;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class HoldingController extends ApiController
{
    public function index(Request $request)
    {
        $query = Holding::myHoldings()->with(['market_data', 'transactions'])
            ->tap($this->search($request, ['symbol']));

        $holdings = QueryBuilder::for($query, $request)
            ->allowedFilters(
                AllowedFilter::exact('symbol'),
                AllowedFilter::exact('portfolio_id'),
                AllowedFilter::exact('reinvest_dividends'),
            )
            ->allowedSorts('symbol', 'quantity', 'average_cost_basis', 'total_cost_basis', 'realized_gain_dollars', 'dividends_earned', 'created_at', 'updated_at')
            ->orderBy('holdings.id')
            ->paginate($this->itemsPerPage($request))
            ->withQueryString();

        return HoldingResource::collection($holdings);
    }

    public function show(Portfolio $portfolio, string $symbol)
    {

        Gate::authorize('readOnly', $portfolio);

        $holding = $portfolio->holdings()->symbol($symbol)->firstOrFail();

        return HoldingResource::make($holding);
    }

    public function update(Request $request, Portfolio $portfolio, string $symbol, UpdateHolding $action)
    {
        $holding = $portfolio->holdings()->symbol($symbol)->firstOrFail();

        return HoldingResource::make($action->handle($holding, $request->all()));
    }
}
