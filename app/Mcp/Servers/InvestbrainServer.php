<?php

declare(strict_types=1);

namespace App\Mcp\Servers;

use App\Mcp\Tools\CreatePortfolioTool;
use App\Mcp\Tools\CreateTransactionTool;
use App\Mcp\Tools\DeletePortfolioTool;
use App\Mcp\Tools\DeleteTransactionTool;
use App\Mcp\Tools\GetAuthenticatedUserTool;
use App\Mcp\Tools\ListHoldingsTool;
use App\Mcp\Tools\ListPortfoliosTool;
use App\Mcp\Tools\ListTransactionsTool;
use App\Mcp\Tools\ShowHoldingTool;
use App\Mcp\Tools\ShowMarketDataTool;
use App\Mcp\Tools\ShowPortfolioTool;
use App\Mcp\Tools\ShowTransactionTool;
use App\Mcp\Tools\UpdateHoldingTool;
use App\Mcp\Tools\UpdatePortfolioTool;
use App\Mcp\Tools\UpdateTransactionTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('Investbrain Server')]
#[Version('0.0.1')]
#[Instructions('Manage Investbrain portfolios, transactions, and holdings, and look up market data. All tools operate on behalf of the authenticated user and only expose data they have access to.')]
class InvestbrainServer extends Server
{
    protected array $tools = [
        GetAuthenticatedUserTool::class,

        ListPortfoliosTool::class,
        CreatePortfolioTool::class,
        ShowPortfolioTool::class,
        UpdatePortfolioTool::class,
        DeletePortfolioTool::class,

        ListTransactionsTool::class,
        CreateTransactionTool::class,
        ShowTransactionTool::class,
        UpdateTransactionTool::class,
        DeleteTransactionTool::class,

        ListHoldingsTool::class,
        ShowHoldingTool::class,
        UpdateHoldingTool::class,

        ShowMarketDataTool::class,
    ];

    protected array $resources = [
        //
    ];

    protected array $prompts = [
        //
    ];
}
