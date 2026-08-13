<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Http\Resources\MarketDataResource;
use App\Models\MarketData;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('show-market-data')]
#[Description('Get current market data for a ticker symbol, such as price, market cap, and dividend yield.')]
#[IsReadOnly]
class ShowMarketDataTool extends Tool
{
    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'symbol' => ['required', 'string'],
        ]);

        try {
            $marketData = MarketData::getMarketData($validated['symbol']);
        } catch (\Throwable) {
            return Response::error('Symbol '.$validated['symbol'].' not found.');
        }

        return Response::text((string) json_encode(MarketDataResource::make($marketData)->resolve()));
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'symbol' => $schema->string()
                ->description('The ticker symbol to look up, e.g. AAPL.')
                ->required(),
        ];
    }
}
