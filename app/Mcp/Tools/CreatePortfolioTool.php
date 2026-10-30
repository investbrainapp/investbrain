<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Actions\Portfolio\CreatePortfolio;
use App\Http\Resources\PortfolioResource;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('create-portfolio')]
#[Description('Create a new portfolio for the authenticated user.')]
class CreatePortfolioTool extends Tool
{
    public function handle(Request $request, CreatePortfolio $action): Response
    {
        $portfolio = $action->handle($request->all());

        return Response::text((string) json_encode(PortfolioResource::make($portfolio)->resolve()));
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'title' => $schema->string()
                ->description('The portfolio title. Must be between 5 and 255 characters.')
                ->required(),

            'notes' => $schema->string()
                ->description('Optional free-form notes about the portfolio.'),

            'wishlist' => $schema->boolean()
                ->description('Whether this portfolio is a wishlist rather than a real holdings portfolio.'),
        ];
    }
}
