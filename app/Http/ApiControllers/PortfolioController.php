<?php

declare(strict_types=1);

namespace App\Http\ApiControllers;

use App\Actions\Portfolio\CreatePortfolio;
use App\Actions\Portfolio\DeletePortfolio;
use App\Actions\Portfolio\UpdatePortfolio;
use App\Http\ApiControllers\Controller as ApiController;
use App\Http\Resources\PortfolioResource;
use App\Models\Portfolio;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class PortfolioController extends ApiController
{
    public function index(Request $request)
    {
        $query = Portfolio::myPortfolios()->with(['users', 'transactions', 'holdings'])
            ->tap($this->search($request, ['title', 'notes']));

        $portfolios = QueryBuilder::for($query, $request)
            ->allowedFilters(
                AllowedFilter::exact('title'),
                AllowedFilter::exact('wishlist'),
                AllowedFilter::exact('holdings.symbol'),
            )
            ->allowedSorts('title', 'wishlist', 'created_at', 'updated_at')
            ->orderBy('portfolios.id')
            ->paginate($this->itemsPerPage($request))
            ->withQueryString();

        return PortfolioResource::collection($portfolios);
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
