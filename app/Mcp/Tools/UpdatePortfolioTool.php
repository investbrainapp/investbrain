<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Actions\Portfolio\UpdatePortfolio;
use App\Http\Resources\PortfolioResource;
use App\Models\Portfolio;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[Name('update-portfolio')]
#[Description('Update an existing portfolio.')]
#[IsIdempotent]
class UpdatePortfolioTool extends Tool
{
    public function handle(Request $request, UpdatePortfolio $action): Response
    {
        $portfolioId = $request->validate([
            'portfolio_id' => ['required', 'string'],
        ])['portfolio_id'];

        $portfolio = Portfolio::find($portfolioId);

        if (! $portfolio) {
            return Response::error('Portfolio not found.');
        }

        $portfolio = $action->handle($portfolio, $request->all());

        return Response::text((string) json_encode(PortfolioResource::make($portfolio)->resolve()));
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'portfolio_id' => $schema->string()
                ->description('The ID of the portfolio to update.')
                ->required(),

            'title' => $schema->string()
                ->description('The portfolio title. Must be between 5 and 255 characters.'),

            'notes' => $schema->string()
                ->description('Optional free-form notes about the portfolio.'),

            'wishlist' => $schema->boolean()
                ->description('Whether this portfolio is a wishlist rather than a real holdings portfolio.'),
        ];
    }
}
