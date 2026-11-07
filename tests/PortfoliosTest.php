<?php

declare(strict_types=1);

namespace Tests;

use App\Models\DailyChange;
use App\Models\Portfolio;
use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonPeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

class PortfoliosTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_is_assigned_to_portfolio_on_create(): void
    {
        $this->actingAs($user = User::factory()->create());

        $portfolio = Portfolio::factory()->create();

        $this->assertEquals($user->id, $portfolio->owner_id);
    }

    public function test_owner_reflects_ownership_set_after_the_users_were_loaded(): void
    {
        $this->actingAs($owner = User::factory()->create());
        $portfolio = Portfolio::factory()->create();
        $portfolio->load('users');

        $newOwner = User::factory()->create();
        $portfolio->users()->sync([$newOwner->id => ['owner' => true]]);

        $this->assertSame($newOwner->id, $portfolio->owner->id);
    }

    public function test_my_portfolios_keeps_earlier_constraints(): void
    {
        $this->actingAs(User::factory()->create());
        Portfolio::factory()->create(['title' => 'Kept']);
        Portfolio::factory()->create(['title' => 'Filtered out']);

        $this->assertSame(['Kept'], Portfolio::where('title', 'Kept')->myPortfolios()->pluck('title')->all());
    }

    public function test_without_wishlists_keeps_earlier_constraints(): void
    {
        $this->actingAs(User::factory()->create());
        Portfolio::factory()->create(['title' => 'Kept', 'wishlist' => false]);
        Portfolio::factory()->create(['title' => 'Filtered out', 'wishlist' => false]);

        $this->assertSame(['Kept'], Portfolio::where('title', 'Kept')->withoutWishlists()->pluck('title')->all());
    }

    public function test_owner_can_be_forced_on_create(): void
    {
        $this->actingAs($user = User::factory()->create());

        $portfolio = Portfolio::factory()->make();
        $portfolio->owner_id = $user->id;
        $portfolio->save();

        $this->assertEquals($user->id, $portfolio->owner_id);
    }

    public function test_owner_cannot_be_changed_on_update(): void
    {
        $this->actingAs($owner = User::factory()->create());

        $interloper = User::factory()->create();

        $portfolio = Portfolio::factory()->create();
        $portfolio->owner_id = $interloper->id;
        $portfolio->save();

        $this->assertEquals($owner->id, $portfolio->owner_id);
    }

    public function test_performance_chart_time_periods_cannot_be_changed_from_the_browser(): void
    {
        $this->actingAs(User::factory()->create());
        $portfolio = Portfolio::factory()->create();

        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::test('portfolio-performance-chart', ['portfolio' => $portfolio])
            ->set('scopeOptions', [['id' => 'YTD', 'name' => 'Year to date', 'method' => 'subDays', 'args' => [1]]]);
    }

    public function test_performance_chart_shows_every_day_for_up_to_a_year(): void
    {
        $this->actingAs(User::factory()->create());
        $portfolio = Portfolio::factory()->create();
        $days = $this->createDailyChanges($portfolio, now()->subYear());

        $points = $this->chartPoints($portfolio);

        $this->assertEquals($days->all(), array_column($points, 0));
    }

    public function test_performance_chart_shows_the_last_day_of_each_week_past_a_year(): void
    {
        $this->actingAs(User::factory()->create());
        $portfolio = Portfolio::factory()->create();
        $days = $this->createDailyChanges($portfolio, now()->subYears(2));

        $points = $this->chartPoints($portfolio);

        $lastDayOfEachWeek = $days->groupBy(fn ($day) => Carbon::parse($day)->format('o-W'))->map->last()->values();

        $this->assertEquals($lastDayOfEachWeek->all(), array_column($points, 0));
        $this->assertEquals($days->last(), end($points)[0]);
    }

    public function test_performance_chart_shows_the_last_day_of_each_month_past_three_years(): void
    {
        $this->actingAs(User::factory()->create());
        $portfolio = Portfolio::factory()->create();
        $days = $this->createDailyChanges($portfolio, now()->subYears(4));

        $points = $this->chartPoints($portfolio);

        $lastDayOfEachMonth = $days->groupBy(fn ($day) => Carbon::parse($day)->format('Y-m'))->map->last()->values();

        $this->assertEquals($lastDayOfEachMonth->all(), array_column($points, 0));

        // each point keeps that day's value rather than adding up the month
        $this->assertEquals($days->search(end($points)[0]), end($points)[1]);
    }

    public function test_performance_chart_shows_market_gain_as_a_percentage_of_cost_basis(): void
    {
        $this->actingAs(User::factory()->create());
        $portfolio = Portfolio::factory()->create();
        Transaction::factory()->buy(2)->lastMonth()->portfolio($portfolio->id)->symbol('ACME')->create(['cost_basis' => 100]);
        $portfolio->daily_change()->update(['total_market_value' => 150]);
        $portfolio->daily_change()->where('date', $portfolio->daily_change()->max('date'))->update(['total_market_value' => 300]);

        [$marketGain, $marketValue, $costBasis] = $this->chartPercentages($portfolio);

        $this->assertSame([-25.0, 50.0], [$marketGain[0], end($marketGain)]);
        $this->assertSame([0.0, 100.0], [$marketValue[0], end($marketValue)]);
        $this->assertSame([0.0, 0.0], [$costBasis[0], end($costBasis)]);
    }

    public function test_performance_chart_shows_no_percentage_when_there_is_nothing_to_compare_against(): void
    {
        $this->actingAs(User::factory()->create());
        $portfolio = Portfolio::factory()->create();
        $days = $this->createDailyChanges($portfolio, now()->subMonth());

        $percentages = $this->chartPercentages($portfolio);

        $noPercentages = array_fill(0, $days->count(), null);
        $this->assertSame([$noPercentages, $noPercentages, $noPercentages], $percentages);
    }

    /**
     * Creates a daily change for each weekday since the start date, valued at its position in the series
     */
    private function createDailyChanges(Portfolio $portfolio, Carbon $start): Collection
    {
        $days = collect(CarbonPeriod::create($start, now()->subDay())->filter('isWeekday'))
            ->map(fn (Carbon $day) => $day->toDateString())
            ->values();

        DailyChange::insert($days->map(fn (string $day, int $index) => [
            'date' => $day,
            'portfolio_id' => $portfolio->id,
            'total_market_value' => $index,
        ])->all());

        return $days;
    }

    /**
     * Market value points of the portfolio's all time performance chart
     *
     * @return array<int, array{0: string, 1: float}>
     */
    private function chartPoints(Portfolio $portfolio): array
    {
        return Livewire::withoutLazyLoading()
            ->test('portfolio-performance-chart', ['portfolio' => $portfolio, 'scope' => 'ALL'])
            ->get('chartSeries')['series'][1]['data'];
    }

    /**
     * Tooltip percentages of the portfolio's all time performance chart, in series order
     *
     * @return array<int, array<int, float|null>>
     */
    private function chartPercentages(Portfolio $portfolio): array
    {
        return Livewire::withoutLazyLoading()
            ->test('portfolio-performance-chart', ['portfolio' => $portfolio, 'scope' => 'ALL'])
            ->get('chartSeries')['percentages'];
    }
}
