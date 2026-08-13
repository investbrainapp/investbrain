<?php

declare(strict_types=1);

use App\Mcp\Servers\InvestbrainServer;
use Laravel\Mcp\Facades\Mcp;

Mcp::web('/mcp/investbrain', InvestbrainServer::class)
    ->middleware([]);
