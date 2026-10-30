<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Http\Resources\HoldingResource;
use App\Models\Portfolio;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('show-holding')]
#[Description('Get a single holding by portfolio ID and ticker symbol.')]
#[IsReadOnly]
class ShowHoldingTool extends Tool
{
    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'portfolio_id' => ['required', 'string'],
            'symbol' => ['required', 'string'],
        ]);

        $portfolio = Portfolio::find($validated['portfolio_id']);

        if (! $portfolio) {
            return Response::error('Portfolio not found.');
        }

        Gate::authorize('readOnly', $portfolio);

        $holding = $portfolio->holdings()->symbol($validated['symbol'])->first();

        if (! $holding) {
            return Response::error('Holding not found.');
        }

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
        ];
    }
}
