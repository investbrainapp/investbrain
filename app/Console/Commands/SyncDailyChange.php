<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class SyncDailyChange extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:daily-change';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Re-calculates daily snapshots of your portfolio\'s daily performance. Use discretion as this is a resource intensive command.';

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
        $this->line('test');
    }
}
