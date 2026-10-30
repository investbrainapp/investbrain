<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Http\Resources\UserResource;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('get-current-user')]
#[Description('Get the currently authenticated user, including their display currency and locale preferences.')]
#[IsReadOnly]
class GetAuthenticatedUserTool extends Tool
{
    public function handle(Request $request): Response
    {
        return Response::text((string) json_encode(UserResource::make($request->user())->resolve()));
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
