<?php

namespace App\Console\Commands;

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
        Portfolio::with('holdings.market_data')->get()->each(function($portfolio){

            $this->line('Capturing daily change for ' . $portfolio->title);

            $total_cost_basis = $portfolio->holdings->sum('total_cost_basis');

            $total_dividends = $portfolio->holdings->sum('dividends_earned');

            $realized_gains = $portfolio->holdings->sum('realized_gain_dollars');

            $total_market_value = $portfolio->holdings->sum(function($holding) {
                return $holding->market_data->market_value * $holding->quantity;
            });

            $portfolio->daily_change()->create([
                'date' => now(),
                'total_market_value' => $total_market_value,
                'total_cost_basis' => $total_cost_basis,
                'total_gain' => $total_market_value - $total_cost_basis,
                'total_dividends_earned' => $total_dividends,
                'realized_gains' => $realized_gains
            ]);
        });
    }
}
