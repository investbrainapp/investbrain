<?php

declare(strict_types=1);

namespace Tests;

use App\Models\MarketData;
use App\Models\Portfolio;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;

class SpotlightTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_search(): void
    {
        $this->getJson(route('spotlight', ['search' => 'acme']))->assertUnauthorized();
    }

    public function test_matches_partial_symbols_and_titles(): void
    {
        $this->actingAs(User::factory()->create());
        $portfolio = Portfolio::factory()->create(['title' => 'Retirement Fund']);
        $this->buy($portfolio, 'MSFT', 'Microsoft Corporation');

        $this->assertSame(['Holding: Microsoft Corporation (MSFT)'], $this->resultNames($this->search('msf')));
        $this->assertSame(['Portfolio: Retirement Fund'], $this->resultNames($this->search('retire')));
    }

    public function test_every_word_must_match_but_in_any_order(): void
    {
        $this->actingAs(User::factory()->create());
        $portfolio = Portfolio::factory()->create(['title' => 'Brokerage']);
        $this->buy($portfolio, 'VTI', 'Vanguard Total Stock Market ETF');
        $this->buy($portfolio, 'VXUS', 'Vanguard Total International Stock ETF');

        $this->assertSame(['Holding: Vanguard Total Stock Market ETF (VTI)'], $this->resultNames($this->search('market vang')));
    }

    public function test_ranks_exact_then_prefix_then_word_prefix_then_anywhere(): void
    {
        $this->actingAs(User::factory()->create());
        $portfolio = Portfolio::factory()->create(['title' => 'Brokerage']);
        $this->buy($portfolio, 'GOOG', 'Alphabet Inc');
        $this->buy($portfolio, 'ACME', 'Acme Tools');
        $this->buy($portfolio, 'TSLA', 'Tesla Inc');
        $this->buy($portfolio, 'T', 'AT&T Inc');

        $this->assertSame([
            'Holding: AT&T Inc (T)',
            'Holding: Tesla Inc (TSLA)',
            'Holding: Acme Tools (ACME)',
            'Holding: Alphabet Inc (GOOG)',
        ], $this->resultNames($this->search('t'), type: 'Holding'));
    }

    public function test_includes_closed_holdings_below_equally_matching_open_holdings(): void
    {
        $this->actingAs(User::factory()->create());
        $portfolio = Portfolio::factory()->create(['title' => 'Bonds']);
        $this->buy($portfolio, 'AAPL', 'Apple Inc');
        Transaction::factory()->sell()->recent()->portfolio($portfolio->id)->symbol('AAPL')->create();
        $this->buy($portfolio, 'AMZN', 'Amazon.com Inc');

        $this->assertSame([
            [
                'name' => 'Holding: Amazon.com Inc (AMZN)',
                'description' => 'Bonds',
                'badge' => null,
                'link' => route('holding.show', ['portfolio' => $portfolio->id, 'symbol' => 'AMZN']),
                'avatar' => null,
            ],
            [
                'name' => 'Holding: Apple Inc (AAPL)',
                'description' => 'Bonds',
                'badge' => ['label' => 'Closed', 'class' => 'badge-error'],
                'link' => route('holding.show', ['portfolio' => $portfolio->id, 'symbol' => 'AAPL']),
                'avatar' => null,
            ],
        ], $this->results($this->search('a'), type: 'Holding'));
    }

    public function test_labels_wishlist_holdings(): void
    {
        $this->actingAs(User::factory()->create());
        $wishlist = Portfolio::factory()->create(['title' => 'Ideas', 'wishlist' => true]);
        $this->buy($wishlist, 'NVDA', 'NVIDIA Corporation');

        $this->search('nvda')
            ->assertJsonPath('0.description', 'Ideas')
            ->assertJsonPath('0.badge', ['label' => 'Wishlist', 'class' => 'badge-secondary']);
    }

    public function test_treats_like_wildcards_literally(): void
    {
        $this->actingAs(User::factory()->create());
        Portfolio::factory()->create(['title' => 'Growth']);
        Portfolio::factory()->create(['title' => '100% Stocks']);

        $this->assertSame(['Portfolio: 100% Stocks'], $this->resultNames($this->search('0%')));
        $this->search('_')->assertExactJson([]);
    }

    public function test_excludes_other_users_portfolios_and_holdings(): void
    {
        $this->actingAs(User::factory()->create());
        $otherPortfolio = Portfolio::factory()->create(['title' => 'Acme Secrets']);
        $this->buy($otherPortfolio, 'ACME', 'Acme Tools');

        $this->actingAs(User::factory()->create());

        $this->search('acme')->assertExactJson([]);
    }

    private function buy(Portfolio $portfolio, string $symbol, string $name): void
    {
        Transaction::factory()->buy()->lastYear()->portfolio($portfolio->id)->symbol($symbol)->create();
        MarketData::where('symbol', $symbol)->update(['name' => $name]);
    }

    private function search(string $search): TestResponse
    {
        return $this->getJson(route('spotlight', ['search' => $search]))->assertOk();
    }

    /**
     * The transaction factory creates a randomly titled portfolio per transaction, so ranking assertions filter by type.
     *
     * @return list<array{name: string, description: ?string, badge: array{label: string, class: string}|null, link: string, avatar: null}>
     */
    private function results(TestResponse $response, ?string $type = null): array
    {
        return array_values(array_filter(
            $response->json(),
            fn (array $result): bool => $type === null || str_starts_with($result['name'], $type.': '),
        ));
    }

    /**
     * @return list<string>
     */
    private function resultNames(TestResponse $response, ?string $type = null): array
    {
        return array_column($this->results($response, $type), 'name');
    }
}
