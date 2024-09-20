<?php

namespace App\Console\Commands;

use App\Models\Split;
use App\Models\Holding;
use Illuminate\Console\Command;

class RefreshSplitData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'refresh:split-data
                                {--force : Refresh all holdings}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Refresh split data from data provider';

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

        if (!($this->option('force') ?? false)) {
            $holdings->where('quantity', '>', 0);
        } 

        foreach ($holdings->get(['symbol']) as $holding) {
            $this->line('Refreshing ' . $holding->symbol);
            
            Split::refreshSplitData($holding->symbol);
        }   
    }
}
