<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Actions\Transaction\CreateTransaction;
use App\Http\Resources\TransactionResource;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('create-transaction')]
#[Description('Record a new BUY or SELL transaction against a portfolio.')]
class CreateTransactionTool extends Tool
{
    public function handle(Request $request, CreateTransaction $action): Response
    {
        $transaction = $action->handle($request->all());

        return Response::text((string) json_encode(TransactionResource::make($transaction)->resolve()));
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'portfolio_id' => $schema->string()
                ->description('The ID of the portfolio this transaction belongs to.')
                ->required(),

            'symbol' => $schema->string()
                ->description('The ticker symbol being transacted, e.g. AAPL.')
                ->required(),

            'transaction_type' => $schema->string()
                ->enum(['BUY', 'SELL'])
                ->description('Whether this is a BUY or SELL transaction.')
                ->required(),

            'date' => $schema->string()
                ->description('The transaction date, formatted as Y-m-d. Cannot be in the future.')
                ->required(),

            'quantity' => $schema->number()
                ->description('The number of shares/units transacted. Must be greater than 0.')
                ->required(),

            'currency' => $schema->string()
                ->description('The currency code for the transaction, e.g. USD.')
                ->required(),

            'cost_basis' => $schema->number()
                ->description('The per-share cost basis. Required for BUY transactions.'),

            'sale_price' => $schema->number()
                ->description('The per-share sale price. Required for SELL transactions.'),
        ];
    }
}
