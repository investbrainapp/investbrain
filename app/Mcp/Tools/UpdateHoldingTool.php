<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Actions\Holding\UpdateHolding;
use App\Http\Resources\HoldingResource;
use App\Models\Portfolio;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[Name('update-holding')]
#[Description('Update a holding, e.g. toggling dividend reinvestment.')]
#[IsIdempotent]
class UpdateHoldingTool extends Tool
{
    public function handle(Request $request, UpdateHolding $action): Response
    {
        $identifiers = $request->validate([
            'portfolio_id' => ['required', 'string'],
            'symbol' => ['required', 'string'],
        ]);

        $portfolio = Portfolio::find($identifiers['portfolio_id']);

        if (! $portfolio) {
            return Response::error('Portfolio not found.');
        }

        $holding = $portfolio->holdings()->symbol($identifiers['symbol'])->first();

        if (! $holding) {
            return Response::error('Holding not found.');
        }

        $holding = $action->handle($holding, $request->all());

        return Response::text((string) json_encode(HoldingResource::make($holding)->resolve()));
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'portfolio_id' => $schema->string()
                ->description('The ID of the portfolio the holding belongs to.')
                ->required(),

            'symbol' => $schema->string()
                ->description('The ticker symbol of the holding, e.g. AAPL.')
                ->required(),

            'reinvest_dividends' => $schema->boolean()
                ->description('Whether dividends for this holding should be automatically reinvested.'),
        ];
    }
}
