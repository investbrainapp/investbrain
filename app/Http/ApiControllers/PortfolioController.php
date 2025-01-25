<?php

declare(strict_types=1);

namespace App\Http\ApiControllers;

use App\Models\Portfolio;
use Illuminate\Support\Facades\Gate;
use HackerEsq\FilterModels\FilterModels;
use App\Http\Resources\PortfolioResource;
use App\Http\Requests\StorePortfolioRequest;
use App\Http\Requests\UpdatePortfolioRequest;
use App\Http\ApiControllers\Controller as ApiController;

class PortfolioController extends ApiController
{
    public function index(FilterModels $filters)
    {
        $filters->setQuery(Portfolio::query());
        $filters->setScopes(['myPortfolios']);
        $filters->setEagerRelations(['users', 'transactions', 'holdings']);
        $filters->setFilterableRelations(['holdings.symbol']);
        $filters->setSearchableColumns(['title', 'notes']);

        return PortfolioResource::collection($filters->paginated());
    }

    public function store(StorePortfolioRequest $request)
    {
        $portfolio = Portfolio::create($request->validated());
        
        return PortfolioResource::make($portfolio);
    }

    public function show(Portfolio $portfolio)
    {
        Gate::authorize('readOnly', $portfolio);

        return PortfolioResource::make($portfolio);
    }

    public function update(UpdatePortfolioRequest $request, Portfolio $portfolio)
    {
        Gate::authorize('fullAccess', $portfolio);

        $portfolio->update($request->validated());

        return PortfolioResource::make($portfolio);
    }

    public function destroy(Portfolio $portfolio)
    {
        Gate::authorize('fullAccess', $portfolio);

        return response()->noContent();
    }
}