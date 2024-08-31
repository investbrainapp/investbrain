<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class CaptureDailyChange extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'daily-change:capture';

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
        User::all()->each(function($user){

            $this->line('Capturing daily change for ' . $user->name);

            $portfolios = $user->portfolios()->withoutWishlists()->with(['holdings.market_data'])->get();

            $total_cost_basis = $portfolios->reduce(function ($carry, $portfolio) {
                return $carry + $portfolio->holdings->sum('total_cost_basis');
            });

            $total_dividends = $portfolios->reduce(function ($carry, $portfolio) {
                return $carry + $portfolio->holdings->sum('dividends_earned');
            });

            $realized_gains = $portfolios->reduce(function ($carry, $portfolio) {
                return $carry + $portfolio->holdings->sum('realized_gain_loss_dollars');
            });

            $total_market_value = $portfolios->reduce(function ($carry, $portfolio) {
                return $carry + $portfolio->holdings->sum(function($holding) {
                    return $holding->market_data->market_value * $holding->quantity;
                }) ;
            });

            $user->daily_changes()->create([
                'date' => now(),
                'total_cost_basis' => $total_cost_basis,
                'total_market_value' => $total_market_value,
                'total_dividends' => $total_dividends,
                'realized_gains' => $realized_gains,
                'total_gain_loss' => $total_market_value - $total_cost_basis
            ]);
        });
    }
}
