<?php

namespace App\Console\Commands;

use App\Models\Holding;
use App\Models\MarketData;
use Illuminate\Console\Command;

class RefreshMarketData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'refresh:market-data
                            {--force : Ignore refresh delay}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Refresh market data from market data provider';

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
        $force = $this->option('force') ?? false;
    
        // get all symbols from market data
        $holdings = Holding::where('quantity', '>', 0)
                    ->select(['symbol'])
                    ->distinct()
                    ->get();

        foreach ($holdings as $holding) {
            $this->line('Refreshing ' . $holding->symbol);

            MarketData::getMarketData($holding->symbol, $force);
        }
    }
}
