<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Http\Resources\TransactionResource;
use App\Models\Transaction;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('show-transaction')]
#[Description('Get a single transaction by its ID.')]
#[IsReadOnly]
class ShowTransactionTool extends Tool
{
    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'transaction_id' => ['required', 'string'],
        ]);

        $transaction = Transaction::find($validated['transaction_id']);

        if (! $transaction) {
            return Response::error('Transaction not found.');
        }

        Gate::authorize('readOnly', $transaction->portfolio);

        return Response::text((string) json_encode(TransactionResource::make($transaction)->resolve()));
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'transaction_id' => $schema->string()
                ->description('The ID of the transaction to retrieve.')
                ->required(),
        ];
    }
}
