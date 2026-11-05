<?php

declare(strict_types=1);

namespace App\Http\ApiControllers;

use App\Http\ApiControllers\Controller as ApiController;
use App\Http\Resources\MarketDataResource;
use App\Models\MarketData;
use App\Rules\SymbolValidationRule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MarketDataController extends ApiController
{
    public function show(Request $request, string $symbol)
    {
        Validator::make(
            ['symbol' => $symbol],
            ['symbol' => SymbolValidationRule::formatRules()],
            SymbolValidationRule::formatMessages('symbol'),
        )->validate();

        try {

            return MarketDataResource::make(
                MarketData::getMarketData($symbol)
            );
        } catch (\Throwable $e) {

            return response([
                'message' => 'Symbol '.$symbol.' not found.',
            ], 404);
        }

    }
}
