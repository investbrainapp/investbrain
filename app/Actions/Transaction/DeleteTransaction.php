<?php

declare(strict_types=1);

namespace App\Actions\Transaction;

use App\Models\Transaction;
use Illuminate\Support\Facades\Gate;

class DeleteTransaction
{
    /**
     * Authorize and delete a transaction. Shared by the API controller and the
     * delete-transaction MCP tool so both enforce identical rules.
     */
    public function handle(Transaction $transaction): void
    {
        Gate::authorize('fullAccess', $transaction->portfolio);

        $transaction->delete();
    }
}
