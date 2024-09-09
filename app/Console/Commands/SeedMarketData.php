<?php

namespace App\Console\Commands;

use App\Models\Split;
use App\Models\Holding;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Database\Seeders\MarketDataSeeder;
use Illuminate\Support\Facades\Artisan;

class SeedMarketData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'seed:market-data
                            {--force : Will seed even if table already has data.}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Seeds baseline market data';

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
        if (
            DB::table('market_data')->count() === 0 
            || $this->option('force', false)
        ) {

            Artisan::call('db:seed', [
                '--class' => MarketDataSeeder::class,
            ]);

            return;
        }

        $this->line('Skipped seeding market data... Table already has data!');
    }
}


