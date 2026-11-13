<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Transaction;
use App\Support\AverageCost;
use Illuminate\Database\Eloquent\Model;

class EnsureCostBasisAddedToSale
{
    public function __invoke(Model $model, callable $next)
    {
        // cost basis is required for sales to calculate realized gains: the average cost of the shares owned that day
        if ($model->transaction_type == 'SELL') {

            $transactions = Transaction::where([
                'portfolio_id' => $model->portfolio_id,
                'symbol' => $model->symbol,
            ])->whereDate('date', '<=', $model->date)
                ->when($model->exists, fn ($query) => $query->whereKeyNot($model->getKey()))
                ->inTradingOrder()
                ->get(['transaction_type', 'quantity', 'cost_basis', 'cost_basis_base']);

            $model->cost_basis = AverageCost::of($transactions)->averageCostBasis();
        }

        return $next($model);
    }
}
