<?php

declare(strict_types=1);

namespace Tests;

use App\Models\DailyChange;
use App\Models\Holding;
use App\Models\Portfolio;
use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonPeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;

class DailyChangeTest extends TestCase
{
    use RefreshDatabase;

    public Portfolio $portfolio;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs($user = User::factory()->create());

        $this->portfolio = Portfolio::factory()->create();
    }

    public function test_daily_change_for_portfolios()
    {
        Transaction::factory(5)->buy()->lastYear()->portfolio($this->portfolio->id)->symbol('AAPL')->create();
        $transaction = Transaction::factory()->sell()->lastMonth()->portfolio($this->portfolio->id)->symbol('AAPL')->create();

        // Run the command
        Artisan::call('capture:daily-change');

        // Assert the daily change was captured for the portfolio
        $this->assertDatabaseHas('daily_change', [
            'portfolio_id' => $this->portfolio->id,
        ]);

        $output = Artisan::output();
        $this->assertStringContainsString('Capturing daily change for', $output);

        $daily_change = DailyChange::where([
            'portfolio_id' => $this->portfolio->id,
        ])->get();

        $this->assertCount(1, $daily_change);

        $quantity = Holding::where('symbol', 'AAPL')->sum('quantity');

        $this->assertEqualsWithDelta(
            $transaction->market_data->market_value_base * $quantity,
            $daily_change->first()->total_market_value,
            0.01
        );

    }

    public function test_can_sync_daily_change_history(): void
    {

        // create some transaction history
        Transaction::factory(5)->buy()->yearsAgo()->portfolio($this->portfolio->id)->symbol('ACME')->create();
        Transaction::factory()->sell()->lastMonth()->portfolio($this->portfolio->id)->symbol('ACME')->create();
        Transaction::factory(5)->buy()->yearsAgo()->portfolio($this->portfolio->id)->symbol('AAPL')->create();
        Transaction::factory(5)->buy()->yearsAgo()->portfolio($this->portfolio->id)->symbol('GOOG')->create();
        Transaction::factory(5)->buy()->yearsAgo()->portfolio($this->portfolio->id)->symbol('FOO')->create();
        Transaction::factory(5)->buy()->yearsAgo()->portfolio($this->portfolio->id)->symbol('BAR')->create();

        // sync
        $this->portfolio->syncDailyChanges();

        // ensure count matches
        $end_date = now()->isBefore(Carbon::parse(config('investbrain.daily_change_time_of_day')))
            ? now()->subDay()
            : now();
        $count_of_daily_changes = $this->portfolio->daily_change()->count('date');
        $days_between_now_and_first_trans = (int) CarbonPeriod::create(
            $this->portfolio->transactions()->min('date'),
            $end_date
        )->filter('isWeekday')
            ->count();

        $this->assertEquals($days_between_now_and_first_trans, $count_of_daily_changes);

        // ensure market value matches
        $holding_performance = $this->portfolio->holdings()->withPerformance()->get();
        $total_market_value = $holding_performance->sum('total_market_value');

        $daily_change = $this->portfolio->daily_change()->orderBy('date')->get()->last();

        $this->assertEqualsWithDelta($total_market_value, $daily_change->total_market_value, 0.01);
    }

    public function test_cost_basis_is_calculated(): void
    {

        $first_transaction = Transaction::factory()->buy()->yearsAgo()->portfolio($this->portfolio->id)->symbol('ACME')->create();
        $this->portfolio->syncDailyChanges();
        $holding = Holding::symbol('ACME')->portfolio($this->portfolio->id)->first();
        $daily_change = DailyChange::withDailyPerformance()
            ->portfolio($this->portfolio->id)
            ->whereDate('daily_change.date', '<=', $first_transaction->date->addDays(2))
            ->whereDate('daily_change.date', '>=', $first_transaction->date->subDays(2))
            ->orderByDesc('daily_change.date')
            ->first();

        dump($daily_change);

        $this->assertEquals($holding->average_cost_basis, $daily_change->total_cost_basis);

        $second_transaction = Transaction::factory()->buy()->lastYear()->portfolio($this->portfolio->id)->symbol('ACME')->create();
        $this->portfolio->syncDailyChanges();
        $daily_change = DailyChange::withDailyPerformance()
            ->portfolio($this->portfolio->id)
            ->whereDate('daily_change.date', '<=', $second_transaction->date->addDays(2))
            ->whereDate('daily_change.date', '>=', $second_transaction->date->subDays(2))
            ->orderByDesc('daily_change.date')
            ->first();

        $this->assertEqualsWithDelta($first_transaction->cost_basis + $second_transaction->cost_basis, $daily_change->total_cost_basis, 0.01);

        $third_transaction = Transaction::factory(2)->sell()->lastMonth()->portfolio($this->portfolio->id)->symbol('ACME')->create()->first();
        $this->portfolio->syncDailyChanges();
        $daily_change = DailyChange::withDailyPerformance()
            ->portfolio($this->portfolio->id)
            ->whereDate('daily_change.date', '<=', $third_transaction->date->addDays(2))
            ->whereDate('daily_change.date', '>=', $third_transaction->date->subDays(2))
            ->orderByDesc('daily_change.date')
            ->first();

        $this->assertEquals(0, $daily_change->total_cost_basis);
    }

    public function test_sales_are_captured_as_realized_gains(): void
    {

        Transaction::factory(5)->buy()->yearsAgo()->portfolio($this->portfolio->id)->symbol('ACME')->create();
        $sale_transaction = Transaction::factory()->sell()->lastMonth()->portfolio($this->portfolio->id)->symbol('ACME')->create();
        Transaction::factory(5)->buy()->yearsAgo()->portfolio($this->portfolio->id)->symbol('AAPL')->create();
        Transaction::factory(5)->buy()->yearsAgo()->portfolio($this->portfolio->id)->symbol('GOOG')->create();
        Transaction::factory(5)->buy()->yearsAgo()->portfolio($this->portfolio->id)->symbol('FOO')->create();
        Transaction::factory(5)->buy()->yearsAgo()->portfolio($this->portfolio->id)->symbol('BAR')->create();

        $this->portfolio->syncDailyChanges();

        $daily_change = DailyChange::query()
            ->portfolio($this->portfolio->id)
            ->whereDate('daily_change.date', '<=', $sale_transaction->date->addDays(2))
            ->whereDate('daily_change.date', '>=', $sale_transaction->date->subDays(2))
            ->orderByDesc('date')
            ->first();

        $realized_gain = ($sale_transaction->sale_price - $sale_transaction->cost_basis) * $sale_transaction->quantity;

        $this->assertEqualsWithDelta($daily_change->realized_gains, $realized_gain, 0.01);

        $day_before = DailyChange::query()
            ->portfolio($this->portfolio->id)
            ->whereDate('daily_change.date', '<', $sale_transaction->date->subDays(1))
            ->orderByDesc('date')
            ->limit(10)
            ->first();

        $this->assertEquals($day_before->realized_gains, 0);

        $after = DailyChange::query()
            ->portfolio($this->portfolio->id)
            ->whereDate('daily_change.date', '<=', $sale_transaction->date->addDays(2))
            ->whereDate('daily_change.date', '>=', $sale_transaction->date->subDays(2))
            ->orderByDesc('date')
            ->first();

        $this->assertEqualsWithDelta($after->realized_gains, $realized_gain, 0.01);
    }

    public function test_dividends_captured_in_daily_change_sync(): void
    {

        Transaction::factory(5)->buy()->yearsAgo()->portfolio($this->portfolio->id)->symbol('ACME')->create();

        Artisan::call('refresh:dividend-data');

        $this->portfolio->syncDailyChanges();

        $holding = Holding::query()->portfolio($this->portfolio->id)->symbol('ACME')->first();
        $dividends = $holding->dividends()->get()->sortBy('date');

        $first_dividend_change = DailyChange::query()
            ->portfolio($this->portfolio->id)
            ->whereDate('daily_change.date', '<=', $dividends->first()->date->addDays(2))
            ->whereDate('daily_change.date', '>=', $dividends->first()->date->subDays(2))
            ->orderByDesc('date')
            ->first();

        $owned = $dividends->first()->purchased - $dividends->first()->sold;

        $this->assertEqualsWithDelta($dividends->first()->dividend_amount * $owned, $first_dividend_change->total_dividends_earned, 0.01);

        $last_dividend_change = DailyChange::query()
            ->portfolio($this->portfolio->id)
            ->whereDate('daily_change.date', '<=', $dividends->last()->date->addDays(2))
            ->whereDate('daily_change.date', '>=', $dividends->last()->date->subDays(2))
            ->orderByDesc('date')
            ->first();

        $total_dividends = $dividends->reduce(function (?float $carry, $dividend) {
            return $carry + ($dividend['dividend_amount'] * ($dividend['purchased'] - $dividend['sold']));
        });

        $owned = $dividends->last()->purchased - $dividends->last()->sold;

        $this->assertEqualsWithDelta($total_dividends, $last_dividend_change->total_dividends_earned, 0.01);
    }
}
