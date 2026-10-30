<?php

declare(strict_types=1);

namespace App\Actions\Transaction;

use App\Models\Portfolio;
use App\Models\Transaction;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class UpdateTransaction
{
    public function __construct(private readonly TransactionRules $rules) {}

    /**
     * Validate, authorize, and update a transaction. Shared by the API controller and
     * the update-transaction MCP tool so both enforce identical rules.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(Transaction $transaction, array $data): Transaction
    {
        $portfolio = isset($data['portfolio_id'])
            ? Portfolio::find($data['portfolio_id'])
            : $transaction->portfolio;

        $validated = Validator::make($data, $this->rules->build($data, $portfolio, $transaction))->validate();

        Gate::authorize('fullAccess', $transaction->portfolio);

        $transaction->update($validated);

        return $transaction;
    }
}
