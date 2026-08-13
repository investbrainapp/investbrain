<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Actions\Portfolio\DeletePortfolio;
use App\Models\Portfolio;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[Name('delete-portfolio')]
#[Description('Permanently delete a portfolio.')]
#[IsDestructive]
#[IsIdempotent]
class DeletePortfolioTool extends Tool
{
    public function handle(Request $request, DeletePortfolio $action): Response
    {
        $validated = $request->validate([
            'portfolio_id' => ['required', 'string'],
        ]);

        $portfolio = Portfolio::find($validated['portfolio_id']);

        if (! $portfolio) {
            return Response::error('Portfolio not found.');
        }

        $action->handle($portfolio);

        return Response::text('Portfolio deleted.');
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'portfolio_id' => $schema->string()
                ->description('The ID of the portfolio to delete.')
                ->required(),
        ];
    }
}
