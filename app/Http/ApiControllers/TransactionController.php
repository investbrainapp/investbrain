<?php

declare(strict_types=1);

namespace App\Http\ApiControllers;

use App\Actions\Transaction\CreateTransaction;
use App\Actions\Transaction\DeleteTransaction;
use App\Actions\Transaction\UpdateTransaction;
use App\Http\ApiControllers\Controller as ApiController;
use App\Http\Resources\TransactionResource;
use App\Models\Transaction;
use HackerEsq\FilterModels\FilterModels;
use Illuminate\Http\Request;
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

    public function store(Request $request, CreateTransaction $action)
    {
        return TransactionResource::make($action->handle($request->all()));
    }

    public function show(Transaction $transaction)
    {
        Gate::authorize('readOnly', $transaction->portfolio);

        return TransactionResource::make($transaction);
    }

    public function update(Request $request, Transaction $transaction, UpdateTransaction $action)
    {
        return TransactionResource::make($action->handle($transaction, $request->all()));
    }

    public function destroy(Transaction $transaction, DeleteTransaction $action)
    {
        $action->handle($transaction);

        return response()->noContent();
    }
}
