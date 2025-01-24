<?php

namespace App\Http\ApiControllers;

use App\Models\Portfolio;
use Illuminate\Http\Request;
use App\Support\FilterRequest;
use App\Http\Resources\PortfolioResource;
use App\Http\ApiControllers\Controller as ApiController;

class PortfolioController extends ApiController
{
    public function index(Request $request)
    {
        $filterRequest = new FilterRequest(Portfolio::class);

        $filterRequest->setScopes(['myPortfolios']);
        $filterRequest->setEagerRelations(['users', 'transactions', 'holdings']);
        $filterRequest->setFilterableRelations(['holdings' => 'symbol', 'transactions' => 'symbol']);
        $filterRequest->setSearchableColumns(['title', 'notes']);
    
        return PortfolioResource::collection($filterRequest->get());
    }
}