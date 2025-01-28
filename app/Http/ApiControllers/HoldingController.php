<?php

namespace App\Http\ApiControllers;

use App\Models\Holding;
use App\Models\Portfolio;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use App\Http\Requests\HoldingRequest;
use App\Http\Resources\HoldingResource;
use HackerEsq\FilterModels\FilterModels;
use App\Http\ApiControllers\Controller as ApiController;

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

    public function update(HoldingRequest $request, Portfolio $portfolio, string $symbol)
    {

        Gate::authorize('fullAccess', $portfolio);

        $holding = $portfolio->holdings()->symbol($symbol)->firstOrFail();

        $holding->update($request->validated());

        return HoldingResource::make($holding);
    }
}