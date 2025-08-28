<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Portfolio;
use App\Models\Transaction;
use Illuminate\Console\Command;

class FixCostBasisForSales extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fix:cost-basis-for-sales
                                    {--portfolio= : The ID of the portfolio to fix.}
                                    {--user= : The user ID of transactions to fix.}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fixes broken costs basis for sale transactions';

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

        if (empty($this->option('user')) && empty($this->option('portfolio'))) {

            $this->error('Must provide at least a user or portfolio.');

            return;
        }

        $transactions = Transaction::where(['transaction_type' => 'SELL']);

        if ($this->option('user')) {

            $portfolios = Portfolio::fullAccess($this->option('user'))->get('id')
                ->pluck('id')
                ->toArray();

            $transactions->whereIn('portfolio_id', $portfolios);

        } else {

            $transactions->where(['portfolio_id' => $this->option('portfolio')]);
        }

        $transactions = $transactions->get();

        $this->line("Fixing cost basis for {$transactions->count()} sale transactions...");

        $transactions->chunk(10)->each(function ($chunk) {

            dispatch(function () use ($chunk) {

                $chunk->each(function ($transaction) {

                    $cost_basis = Transaction::where([
                        'portfolio_id' => $transaction->portfolio_id,
                        'symbol' => $transaction->symbol,
                        'transaction_type' => 'BUY',
                    ])->whereDate('date', '<=', $transaction->date)
                        ->selectRaw('SUM(transactions.cost_basis * transactions.quantity) as total_cost_basis')
                        ->selectRaw('SUM(transactions.quantity) as total_quantity')
                        ->first();

                    $average_cost_basis = empty($cost_basis->total_quantity)
                        ? 0
                        : $cost_basis->total_cost_basis / $cost_basis->total_quantity;

                    $transaction->cost_basis = $average_cost_basis ?? 0;

                    $transaction->save();
                });
            });
        });

        $this->line('Done!');
    }
}
