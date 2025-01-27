<?php

namespace App\Http\ApiControllers;

use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use HackerEsq\FilterModels\FilterModels;
use App\Http\Requests\TransactionRequest;
use App\Http\Resources\TransactionResource;
use App\Http\ApiControllers\Controller as ApiController;

class TransactionController extends ApiController
{
    public function index(FilterModels $filters)
    {

        $filters->setQuery(Transaction::query());
        $filters->setScopes(['myTransactions']);
        $filters->setSearchableColumns(['symbol']);

        return TransactionResource::collection($filters->paginated());
    }

    public function store(TransactionRequest $request)
    {
        $transaction = Transaction::create($request->validated());
        
        return TransactionResource::make($transaction);
    }

    public function show(Transaction $transaction)
    {
        Gate::authorize('readOnly', $transaction);

        return TransactionResource::make($transaction);
    }

    public function update(TransactionRequest $request, Transaction $transaction)
    {
        Gate::authorize('fullAccess', $transaction);

        $transaction->update($request->validated());

        return TransactionResource::make($transaction);
    }

    public function destroy(Transaction $transaction)
    {
        Gate::authorize('fullAccess', $transaction);

        $transaction->delete();

        return response()->noContent();
    }
}