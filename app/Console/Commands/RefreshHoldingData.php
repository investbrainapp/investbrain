<?php

namespace App\Console\Commands;

use App\Models\Dividend;
use App\Models\Holding;
use App\Models\MarketData;
use App\Models\Transaction;
use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;

class RefreshHoldingData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'holding-data:refresh';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Refresh holdings';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        // get all holdings
        $holdings = Holding::get();

        foreach ($holdings as $holding) {
            $this->line('Refreshing ' . $holding->symbol);

            $query = Transaction::where([
                'portfolio_id' => $holding->portfolio_id,
                'symbol' => $holding->symbol,
            ])->selectRaw('SUM(CASE WHEN transaction_type = "BUY" THEN quantity ELSE 0 END) AS `qty_purchases`')
            ->selectRaw('SUM(CASE WHEN transaction_type = "SELL" THEN quantity ELSE 0 END) AS `qty_sales`')
            ->selectRaw('SUM(CASE WHEN transaction_type = "BUY" THEN (quantity * cost_basis) ELSE 0 END) AS `cost_basis`')
            ->selectRaw('SUM(CASE WHEN transaction_type = "SELL" THEN ((sale_price - cost_basis) * quantity) ELSE 0 END) AS `realized_gains`')
            ->first();

            $total_quantity = $query->qty_purchases - $query->qty_sales;
            $average_cost_basis = $query->qty_purchases > 0 
                                    ? $query->cost_basis / $query->qty_purchases 
                                    : 0;

            // pull dividend data joined with holdings/transactions
            $dividends = Dividend::where([
                'dividends.symbol' => $holding->symbol,
            ])
            ->select(['holdings.portfolio_id', 'dividends.date', 'dividends.symbol', 'dividends.dividend_amount'])
            ->selectRaw('@purchased:=(SELECT coalesce(SUM(quantity),0) FROM transactions WHERE transactions.transaction_type = "BUY" AND transactions.symbol = dividends.symbol AND date(transactions.date) <= date(dividends.date) AND holdings.portfolio_id = transactions.portfolio_id ) AS `purchased`')
            ->selectRaw('@sold:=(SELECT coalesce(SUM(quantity),0) FROM transactions WHERE transactions.transaction_type = "SELL" AND transactions.symbol = dividends.symbol AND date(transactions.date) <= date(dividends.date)  AND holdings.portfolio_id = transactions.portfolio_id ) AS `sold`')
            ->selectRaw('@owned:=(@purchased - @sold) AS `owned`')
            ->selectRaw('@dividends_received:=(@owned * dividends.dividend_amount) AS `dividends_received`')
            ->join('transactions', 'transactions.symbol', 'dividends.symbol')
            ->join('holdings', 'transactions.portfolio_id', 'holdings.portfolio_id')
            ->groupBy(['holdings.portfolio_id', 'dividends.date', 'dividends.symbol', 'dividends.dividend_amount'])
            ->get();

            // update holding
            $holding->fill([
                'quantity' => $total_quantity,
                'average_cost_basis' => $average_cost_basis,
                'total_cost_basis' => $total_quantity * $average_cost_basis,
                'realized_gain_dollars' => $query->realized_gains,
                'dividends_earned' => $dividends->where('portfolio_id', $holding->portfolio_id)->sum('dividends_received')
            ]);

            $holding->save();
        }
    }
}
