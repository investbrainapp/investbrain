<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Http\Resources\PortfolioResource;
use App\Models\Portfolio;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('show-portfolio')]
#[Description('Get a single portfolio by its ID.')]
#[IsReadOnly]
class ShowPortfolioTool extends Tool
{
    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'portfolio_id' => ['required', 'string'],
        ]);

        $portfolio = Portfolio::find($validated['portfolio_id']);

        if (! $portfolio) {
            return Response::error('Portfolio not found.');
        }

        Gate::authorize('readOnly', $portfolio);

        return Response::text((string) json_encode(PortfolioResource::make($portfolio)->resolve()));
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'portfolio_id' => $schema->string()
                ->description('The ID of the portfolio to retrieve.')
                ->required(),
        ];
    }
}
