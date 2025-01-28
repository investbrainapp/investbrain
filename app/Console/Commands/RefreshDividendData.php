<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Dividend;
use App\Models\Holding;
use Illuminate\Console\Command;

class RefreshDividendData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'refresh:dividend-data
                                {--force : Refresh all holdings}
                                {--user= : Limit refresh to user\'s holdings}';

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
        $holdings = Holding::distinct();

        if (! ($this->option('force') ?? false)) {
            $holdings->where('quantity', '>', 0);
        }

        if ($this->option('user')) {
            $holdings->myHoldings($this->option('user'));
        }

        foreach ($holdings->get(['symbol']) as $holding) {
            $this->line('Refreshing '.$holding->symbol);

            Dividend::refreshDividendData($holding->symbol);
        }
    }
}
