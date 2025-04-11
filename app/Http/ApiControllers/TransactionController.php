<?php

declare(strict_types=1);

namespace App\Http\ApiControllers;

use App\Http\ApiControllers\Controller as ApiController;
use App\Http\Requests\TransactionRequest;
use App\Http\Resources\TransactionResource;
use App\Models\Transaction;
use HackerEsq\FilterModels\FilterModels;
use Illuminate\Support\Facades\Gate;

class TransactionController extends ApiController
{
    public function index(FilterModels $filters)
    {

        $filters->setQuery(Transaction::query());
        $filters->setScopes(['myTransactions']);
        $filters->setEagerRelations(['market_data']);
        $filters->setSearchableColumns(['symbol']);

        return TransactionResource::collection($filters->paginated());
    }

    public function store(TransactionRequest $request)
    {
        Gate::authorize('fullAccess', $request->portfolio);

        $transaction = Transaction::create($request->validated());

        return TransactionResource::make($transaction);
    }

    public function show(Transaction $transaction)
    {
        Gate::authorize('readOnly', $transaction->portfolio);

        return TransactionResource::make($transaction);
    }

    public function update(TransactionRequest $request, Transaction $transaction)
    {
        Gate::authorize('fullAccess', $transaction->portfolio);

        $transaction->update($request->validated());

        return TransactionResource::make($transaction);
    }

    public function destroy(Transaction $transaction)
    {
        Gate::authorize('fullAccess', $transaction->portfolio);

        $transaction->delete();

        return response()->noContent();
    }
}
