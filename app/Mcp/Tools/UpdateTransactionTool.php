<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Actions\Transaction\UpdateTransaction;
use App\Http\Resources\TransactionResource;
use App\Models\Transaction;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('update-transaction')]
#[Description('Update an existing transaction.')]
class UpdateTransactionTool extends Tool
{
    public function handle(Request $request, UpdateTransaction $action): Response
    {
        $transactionId = $request->validate([
            'transaction_id' => ['required', 'string'],
        ])['transaction_id'];

        $transaction = Transaction::find($transactionId);

        if (! $transaction) {
            return Response::error('Transaction not found.');
        }

        $transaction = $action->handle($transaction, $request->all());

        return Response::text((string) json_encode(TransactionResource::make($transaction)->resolve()));
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'transaction_id' => $schema->string()
                ->description('The ID of the transaction to update.')
                ->required(),

            'portfolio_id' => $schema->string()
                ->description('The ID of the portfolio this transaction belongs to.'),

            'symbol' => $schema->string()
                ->description('The ticker symbol being transacted, e.g. AAPL.'),

            'transaction_type' => $schema->string()
                ->enum(['BUY', 'SELL'])
                ->description('Whether this is a BUY or SELL transaction.'),

            'date' => $schema->string()
                ->description('The transaction date, formatted as Y-m-d. Cannot be in the future.'),

            'quantity' => $schema->number()
                ->description('The number of shares/units transacted. Must be greater than 0.'),

            'currency' => $schema->string()
                ->description('The currency code for the transaction, e.g. USD.'),

            'cost_basis' => $schema->number()
                ->description('The per-share cost basis. Required for BUY transactions.'),

            'sale_price' => $schema->number()
                ->description('The per-share sale price. Required for SELL transactions.'),
        ];
    }
}
