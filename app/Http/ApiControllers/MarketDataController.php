<?php

declare(strict_types=1);

namespace App\Http\ApiControllers;

use App\Models\MarketData;
use Illuminate\Http\Request;
use App\Http\Resources\MarketDataResource;
use App\Http\ApiControllers\Controller as ApiController;

class MarketDataController extends ApiController
{
    public function show(Request $request, string $symbol)
    {

        return MarketDataResource::make(
            MarketData::getMarketData($symbol)
        );
    }
}