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
    protected $signature = 'sync:holdings
                            {--user= : Limit refresh to user\'s holdings}';

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
        $holdings = Holding::query();

        if ($this->option('user')) {
            $holdings->myHoldings($this->option('user'));
        }

        foreach ($holdings->get() as $holding) {
            $this->line('Refreshing '.$holding->symbol);

            $holding->syncTransactionsAndDividends();
        }
    }
}
