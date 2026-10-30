<?php

declare(strict_types=1);

namespace App\Http\ApiControllers;

use App\Actions\Portfolio\CreatePortfolio;
use App\Actions\Portfolio\DeletePortfolio;
use App\Actions\Portfolio\UpdatePortfolio;
use App\Http\ApiControllers\Controller as ApiController;
use App\Http\Resources\PortfolioResource;
use App\Models\Portfolio;
use HackerEsq\FilterModels\FilterModels;
use Illuminate\Http\Request;
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

    public function store(Request $request, CreatePortfolio $action)
    {
        return PortfolioResource::make($action->handle($request->all()));
    }

    public function show(Portfolio $portfolio)
    {
        Gate::authorize('readOnly', $portfolio);

        return PortfolioResource::make($portfolio);
    }

    public function update(Request $request, Portfolio $portfolio, UpdatePortfolio $action)
    {
        return PortfolioResource::make($action->handle($portfolio, $request->all()));
    }

    public function destroy(Portfolio $portfolio, DeletePortfolio $action)
    {
        $action->handle($portfolio);

        return response()->noContent();
    }
}
