<?php

declare(strict_types=1);

use App\Mcp\Servers\InvestbrainServer;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Facades\Mcp;
use Laravel\Passport\Http\Middleware\CheckToken;

if (! config('services.mcp_server_enabled')) {
    return;
}

// client registration is public by design (RFC 7591) and each one creates an OAuth client, so limit how often it can be called
Route::middleware('throttle:10,1')->group(function () {
    Mcp::oauthRoutes();
});

Mcp::web('/mcp', InvestbrainServer::class)
    ->middleware(['auth:api', CheckToken::using('mcp:use')]);
