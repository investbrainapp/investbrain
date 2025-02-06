<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Currency;
use Illuminate\Console\Command;

class RefreshCurrencyData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'refresh:currency-data
                                {--force : Refresh of currency data}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Refresh currency data from data provider';

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

        Currency::refreshCurrencyData($this->option('force') ?? false);
    }
}
