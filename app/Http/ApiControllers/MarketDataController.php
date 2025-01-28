<?php

declare(strict_types=1);

namespace App\Http\ApiControllers;

use App\Http\ApiControllers\Controller as ApiController;
use App\Http\Resources\MarketDataResource;
use App\Models\MarketData;
use Illuminate\Http\Request;

class MarketDataController extends ApiController
{
    public function show(Request $request, string $symbol)
    {

        return MarketDataResource::make(
            MarketData::getMarketData($symbol)
        );
    }
}
