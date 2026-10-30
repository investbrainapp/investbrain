<?php

declare(strict_types=1);

namespace App\Http\ApiControllers;

use App\Actions\Holding\UpdateHolding;
use App\Http\ApiControllers\Controller as ApiController;
use App\Http\Resources\HoldingResource;
use App\Models\Holding;
use App\Models\Portfolio;
use HackerEsq\FilterModels\FilterModels;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class HoldingController extends ApiController
{
    public function index(FilterModels $filters)
    {

        $filters->setQuery(Holding::query());
        $filters->setScopes(['myHoldings']);
        $filters->setEagerRelations(['market_data', 'transactions']);
        $filters->setSearchableColumns(['symbol']);

        return HoldingResource::collection($filters->paginated());
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
