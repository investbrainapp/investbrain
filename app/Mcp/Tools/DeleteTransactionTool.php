<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Actions\Transaction\DeleteTransaction;
use App\Models\Transaction;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[Name('delete-transaction')]
#[Description('Permanently delete a transaction.')]
#[IsDestructive]
#[IsIdempotent]
class DeleteTransactionTool extends Tool
{
    public function handle(Request $request, DeleteTransaction $action): Response
    {
        $validated = $request->validate([
            'transaction_id' => ['required', 'string'],
        ]);

        $transaction = Transaction::find($validated['transaction_id']);

        if (! $transaction) {
            return Response::error('Transaction not found.');
        }

        $action->handle($transaction);

        return Response::text('Transaction deleted.');
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'transaction_id' => $schema->string()
                ->description('The ID of the transaction to delete.')
                ->required(),
        ];
    }
}
