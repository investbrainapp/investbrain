<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Portfolio;
use Illuminate\Console\Command;
use Illuminate\Contracts\Console\PromptsForMissingInput;

use function Laravel\Prompts\search;

class SyncDailyChange extends Command implements PromptsForMissingInput
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:daily-change
                                {portfolio_id : The ID of the portfolio to re-calculate.}
                                {--force : Don\'t ask to confirm.}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Re-calculates daily snapshots of your portfolio\'s daily performance. Use discretion as this is a resource intensive command.';

    /**
     * Prompt for missing input arguments using the returned questions.
     *
     * @return array<string, string>
     */
    protected function promptForMissingArgumentsUsing(): array
    {
        return [
            'portfolio_id' => fn () => search(
                label: 'Choose the portfolio you wish to re-calculate:',
                placeholder: 'E.g. My favorite stocks',
                options: fn ($value) => strlen($value) > 0
                    ? Portfolio::where('title', 'like', "%{$value}%")->pluck('title', 'id')->all()
                    : []
            ),
        ];
    }

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
        try {

            $portfolio = Portfolio::findOrFail($this->argument('portfolio_id'));

            $this->line('Syncing daily change history... This may take a moment.');

            $portfolio->syncDailyChanges();

            $this->line('Awesome! Daily change history for '.$portfolio->title.' has been completed.');

        } catch (\Throwable $e) {

            $this->error($e->getMessage());
        }
    }
}
