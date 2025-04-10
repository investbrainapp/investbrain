<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Transaction;
use Illuminate\Database\Eloquent\Model;

class EnsureCostBasisAddedToSale
{
    public function __invoke(Model $model, callable $next)
    {
        // cost basis is required for sales to calculate realized gains
        if ($model->transaction_type == 'SELL') {

            $average_cost_basis = Transaction::where([
                'portfolio_id' => $model->portfolio_id,
                'symbol' => $model->symbol,
                'transaction_type' => 'BUY',
            ])->whereDate('date', '<=', $model->date)
                ->average('cost_basis');

            $model->cost_basis = $average_cost_basis ?? 0;
        }

        return $next($model);
    }
}
