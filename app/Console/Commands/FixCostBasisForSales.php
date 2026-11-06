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

                // saving a sale runs EnsureCostBasisAddedToSale, which recalculates its cost basis
                $chunk->each->save();
            });
        });

        $this->line('Done!');
    }
}
