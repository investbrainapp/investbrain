<?php

declare(strict_types=1);

namespace App\Http\ApiControllers;

use App\Models\Portfolio;
use Illuminate\Http\Request;
use HackerEsq\FilterModels\FilterModels;
use App\Http\Resources\PortfolioResource;
use App\Http\ApiControllers\Controller as ApiController;

class PortfolioController extends ApiController
{
    public function index(FilterModels $filters)
    {

        $filters->setQuery(Portfolio::query());
        $filters->setScopes(['myPortfolios']);
        $filters->setEagerRelations(['users', 'transactions', 'holdings']);
        $filters->setFilterableRelations(['holdings' => 'symbol', 'transactions' => 'symbol']);
        $filters->setSearchableColumns(['title', 'notes']);

        return PortfolioResource::collection($filters->paginated());
    }
}