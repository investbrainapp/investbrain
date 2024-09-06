<?php

namespace App\Console\Commands;

use App\Models\Holding;
use Illuminate\Console\Command;

class SyncHoldingData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'holding-data:sync';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Syncs holdings with transactions and dividends';

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

            $holding->syncTransactionsAndDividends();
        }
    }
}
