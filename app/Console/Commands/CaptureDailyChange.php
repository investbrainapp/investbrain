<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Holding;
use App\Models\Portfolio;
use Illuminate\Console\Command;

class CaptureDailyChange extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'capture:daily-change';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Capture summary of daily change for user\'s holdings';

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
        Portfolio::with('holdings.market_data')->get()->each(function ($portfolio) {

            $this->line('Capturing daily change for '.$portfolio->title);

            $metrics = Holding::query()
                ->portfolio($portfolio->id)
                ->getPortfolioMetrics(config('investbrain.base_currency'));

            $total_cost_basis = $metrics->get('total_cost_basis');
            $total_market_value = $metrics->get('total_market_value');

            $portfolio->daily_change()->create([
                'date' => now(),
                'total_market_value' => $total_market_value,
                'total_cost_basis' => $total_cost_basis,
                'total_market_gain' => $total_market_value - $total_cost_basis,
                'total_dividends_earned' => $metrics->get('total_dividends_earned'),
                'realized_gains' => $metrics->get('realized_gain_dollars'),
            ]);
        });
    }
}
