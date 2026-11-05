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
        $portfolio = is_string($data['portfolio_id'] ?? null) ? Portfolio::find($data['portfolio_id']) : null;

        // check access before validating: the quantity rule reads the portfolio's transactions, so validating
        // first would let the response depend on what someone else's portfolio holds. A portfolio that doesn't
        // exist is left for validation to report.
        if ($portfolio) {
            Gate::authorize('fullAccess', $portfolio);
        }

        $validated = Validator::make($data, $this->rules->build($data, $portfolio))->validate();

        return Transaction::create($validated);
    }
}
