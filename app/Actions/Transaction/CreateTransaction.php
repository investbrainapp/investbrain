<?php

declare(strict_types=1);

namespace App\Actions\Transaction;

use App\Models\Portfolio;
use App\Models\Transaction;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class CreateTransaction
{
    public function __construct(private readonly TransactionRules $rules) {}

    /**
     * Validate, authorize, and record a new transaction. Shared by the API controller
     * and the create-transaction MCP tool so both enforce identical rules.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data): Transaction
    {
        $portfolio = Portfolio::find($data['portfolio_id'] ?? null);

        $validated = Validator::make($data, $this->rules->build($data, $portfolio))->validate();

        Gate::authorize('fullAccess', $portfolio);

        return Transaction::create($validated);
    }
}
