<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Currency;
use Illuminate\Database\Eloquent\Model;

class ConvertToMarketDataCurrency
{
    public function __invoke(Model $model, callable $next)
    {
        if (! is_null($model->currency) && $model->currency !== $model->market_data->currency) {

            // convert to market data currency
            $model->cost_basis = Currency::convert(
                value: $model->cost_basis,
                from: $model->currency,
                to: $model->market_data->currency,
                date: $model->date
            );

            if ($model->transaction_type == 'SELL') {

                $model->sale_price = Currency::convert(
                    value: $model->sale_price,
                    from: $model->currency,
                    to: $model->market_data->currency,
                    date: $model->date
                );
            }
        }

        // currency cannot be saved to the database - we already know market_data.currency anyway
        unset($model->currency);

        return $next($model);
    }
}
