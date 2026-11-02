<?php

declare(strict_types=1);

use App\Mcp\Servers\InvestbrainServer;
use Laravel\Mcp\Facades\Mcp;
use Laravel\Passport\Http\Middleware\CheckToken;

if (! config('services.mcp_server_enabled')) {
    return;
}

Mcp::oauthRoutes();

Mcp::web('/mcp', InvestbrainServer::class)
    ->middleware(['auth:api', CheckToken::using('mcp:use')]);
