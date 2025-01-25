<?php

namespace App\Http\ApiControllers;

use App\Models\Holding;
use Illuminate\Http\Request;
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
}