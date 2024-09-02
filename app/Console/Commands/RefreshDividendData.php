<?php

namespace App\Console\Commands;

use App\Models\Holding;
use App\Models\Dividend;
use Illuminate\Console\Command;

class RefreshDividendData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dividend-data:refresh';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Refresh dividend data from data provider';

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
        // $holdings = Holding::where('quantity', '>', 0)->distinct()->get(['symbol']);
        $holdings = Holding::distinct()->get(['symbol']);

        foreach ($holdings as $holding) {
            $this->line('Refreshing ' . $holding->symbol);
            
            Dividend::refreshDividendData($holding->symbol);
        }
    }
}
