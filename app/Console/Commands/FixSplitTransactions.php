<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Split;
use Illuminate\Console\Command;

class FixSplitTransactions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fix:split-transactions';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Converts legacy split transactions into split adjusted transactions';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->line('Repairing split transactions...');

        $results = Split::repairLegacySplitTransactions();

        $this->line("Repaired {$results['repaired']} split transactions.");
        $this->line("Adjusted dividends for {$results['dividends_adjusted']} splits.");

        if ($results['skipped'] > 0) {

            $this->warn("Skipped {$results['skipped']} split transactions because their split ratio could not be determined.");
        }

        $this->line('Done!');

        return self::SUCCESS;
    }
}
