<?php

declare(strict_types=1);

namespace Tests\Api;

use App\Models\MarketData;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MarketDataTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    public function test_can_get_market_data_for_symbol(): void
    {
        MarketData::getMarketData('AAPL');

        $this->actingAs($this->user)
            ->getJson(route('api.market-data.show', ['symbol' => 'AAPL']))
            ->assertOk()
            ->assertJsonStructure([
                'symbol',
                'name',
                'market_value',
                'fifty_two_week_low',
                'fifty_two_week_high',
                'last_dividend_date',
                'last_dividend_amount',
                'dividend_yield',
                'market_cap',
                'trailing_pe',
                'forward_pe',
                'book_value',
                'created_at',
                'updated_at',
            ]);
    }

    public function test_market_data_returns_correct_symbol(): void
    {
        $this->actingAs($this->user)
            ->getJson(route('api.market-data.show', ['symbol' => 'ACME']))
            ->assertSuccessful()
            ->assertJsonFragment([
                'symbol' => 'ACME',
            ]);
    }

    public function test_market_data_response_has_expected_fields(): void
    {
        MarketData::getMarketData('MSFT');

        $this->actingAs($this->user)
            ->getJson(route('api.market-data.show', ['symbol' => 'MSFT']))
            ->assertOk()
            ->assertJsonPath('symbol', 'MSFT')
            ->assertJsonPath('market_value', 230.19);
    }

    public function test_cannot_access_market_data_when_unauthenticated(): void
    {
        $this->getJson(route('api.market-data.show', ['symbol' => 'AAPL']))->assertUnauthorized();
    }

    public function test_market_data_can_include_optional_market_sentiment(): void
    {
        config()->set('services.adanos.key', 'test-key');

        MarketData::getMarketData('AAPL');

        Http::fake([
            'https://api.adanos.org/reddit/stocks/v1/compare*' => Http::response([
                'stocks' => [[
                    'ticker' => 'AAPL',
                    'buzz_score' => 55.4,
                    'bullish_pct' => 62,
                    'mentions' => 120,
                ]],
            ]),
            'https://api.adanos.org/x/stocks/v1/compare*' => Http::response([
                'stocks' => [[
                    'ticker' => 'AAPL',
                    'buzz_score' => 60.0,
                    'bullish_pct' => 58,
                    'mentions' => 94,
                ]],
            ]),
            'https://api.adanos.org/news/stocks/v1/compare*' => Http::response([
                'stocks' => [[
                    'ticker' => 'AAPL',
                    'buzz_score' => 48.0,
                    'bullish_pct' => 61,
                    'mentions' => 37,
                ]],
            ]),
            'https://api.adanos.org/polymarket/stocks/v1/compare*' => Http::response([
                'stocks' => [[
                    'ticker' => 'AAPL',
                    'buzz_score' => 50.0,
                    'bullish_pct' => 64,
                    'trade_count' => 512,
                ]],
            ]),
        ]);

        $this->actingAs($this->user)
            ->getJson(route('api.market-data.show', ['symbol' => 'AAPL']))
            ->assertOk()
            ->assertJsonPath('market_sentiment.average_buzz', 53.35)
            ->assertJsonPath('market_sentiment.average_bullish_pct', 61.25)
            ->assertJsonPath('market_sentiment.coverage', 4)
            ->assertJsonPath('market_sentiment.source_alignment', 'aligned')
            ->assertJsonPath('market_sentiment.sources.reddit.mentions', 120)
            ->assertJsonPath('market_sentiment.sources.polymarket.trade_count', 512);
    }

    public function test_market_data_omits_market_sentiment_when_adanos_is_not_configured(): void
    {
        MarketData::getMarketData('AAPL');

        Http::fake();

        $this->actingAs($this->user)
            ->getJson(route('api.market-data.show', ['symbol' => 'AAPL']))
            ->assertOk()
            ->assertJsonMissingPath('market_sentiment');

        Http::assertNothingSent();
    }
}
