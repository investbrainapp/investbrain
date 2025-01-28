<?php

declare(strict_types=1);

namespace App\Http\ApiControllers;

use App\Http\ApiControllers\Controller as ApiController;
use App\Http\Requests\PortfolioRequest;
use App\Http\Resources\PortfolioResource;
use App\Models\Portfolio;
use HackerEsq\FilterModels\FilterModels;
use Illuminate\Support\Facades\Gate;

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

    public function store(PortfolioRequest $request)
    {
        $portfolio = Portfolio::create($request->validated());

        return PortfolioResource::make($portfolio);
    }

    public function show(Portfolio $portfolio)
    {
        Gate::authorize('readOnly', $portfolio);

        return PortfolioResource::make($portfolio);
    }

    public function update(PortfolioRequest $request, Portfolio $portfolio)
    {
        Gate::authorize('fullAccess', $portfolio);

        $portfolio->update($request->validated());

        return PortfolioResource::make($portfolio);
    }

    public function destroy(Portfolio $portfolio)
    {
        Gate::authorize('fullAccess', $portfolio);

        $portfolio->delete();

        return response()->noContent();
    }
}
