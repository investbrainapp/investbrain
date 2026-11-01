<?php

declare(strict_types=1);

namespace App\Http\ApiControllers;

use App\Actions\Transaction\CreateTransaction;
use App\Actions\Transaction\DeleteTransaction;
use App\Actions\Transaction\UpdateTransaction;
use App\Http\ApiControllers\Controller as ApiController;
use App\Http\Resources\TransactionResource;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class TransactionController extends ApiController
{
    public function index(Request $request)
    {
        $query = Transaction::myTransactions()->with(['market_data'])
            ->tap($this->search($request, ['symbol']));

        $transactions = QueryBuilder::for($query, $request)
            ->allowedFilters(
                AllowedFilter::exact('symbol'),
                AllowedFilter::exact('portfolio_id'),
                AllowedFilter::exact('transaction_type'),
                AllowedFilter::exact('currency'),
                AllowedFilter::exact('split'),
                AllowedFilter::exact('reinvested_dividend'),
            )
            ->allowedSorts('symbol', 'transaction_type', 'quantity', 'cost_basis', 'sale_price', 'date', 'created_at', 'updated_at')
            ->orderBy('transactions.id')
            ->paginate($this->itemsPerPage($request))
            ->withQueryString();

        return TransactionResource::collection($transactions);
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
