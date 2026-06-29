<?php

declare(strict_types=1);

namespace Tests;

use App\Models\MarketSentiment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

class MarketSentimentTest extends TestCase
{
    use RefreshDatabase;

    public function test_market_sentiment_is_disabled_without_api_key(): void
    {
        config()->set('services.adanos.key', null);

        Http::fake();

        $result = MarketSentiment::getMarketSentiment('AAPL');

        $this->assertNull($result);
        Http::assertNothingSent();
    }

    public function test_market_sentiment_fetches_and_persists_source_metrics(): void
    {
        config()->set('services.adanos.key', 'test-key');

        Http::fake($this->fakeSentimentResponses());

        $sentiment = MarketSentiment::getMarketSentiment('AAPL');

        $this->assertNotNull($sentiment);
        $this->assertSame('AAPL', $sentiment->symbol);
        $this->assertSame(4, $sentiment->coverage);
        $this->assertSame('aligned', $sentiment->source_alignment);
        $this->assertEquals(53.35, $sentiment->average_buzz);
        $this->assertEquals(61.25, $sentiment->average_bullish_pct);
        $this->assertSame(120, $sentiment->reddit_mentions);
        $this->assertSame(94, $sentiment->x_mentions);
        $this->assertSame(37, $sentiment->news_mentions);
        $this->assertSame(512, $sentiment->polymarket_trade_count);

        $this->assertDatabaseHas('market_sentiment', [
            'symbol' => 'AAPL',
            'coverage' => 4,
            'source_alignment' => 'aligned',
        ]);

        Http::assertSentCount(4);
    }

    public function test_market_sentiment_uses_database_cache_until_refresh_window_expires(): void
    {
        config()->set('services.adanos.key', 'test-key');
        config()->set('services.adanos.market_sentiment_refresh', 360);

        Http::fake($this->fakeSentimentResponses());

        $first = MarketSentiment::getMarketSentiment('AAPL');
        $second = MarketSentiment::getMarketSentiment('AAPL');

        $this->assertNotNull($first);
        $this->assertNotNull($second);
        $this->assertTrue($first->is($second));

        Http::assertSentCount(4);
    }

    /**
     * @return array<string, \Illuminate\Http\Client\Response>
     */
    protected function fakeSentimentResponses(): array
    {
        return [
            'https://api.adanos.org/reddit/stocks/v1/compare*' => Http::response([
                'period_days' => 7,
                'stocks' => [[
                    'ticker' => 'AAPL',
                    'buzz_score' => 55.4,
                    'bullish_pct' => 62,
                    'mentions' => 120,
                ]],
            ]),
            'https://api.adanos.org/x/stocks/v1/compare*' => Http::response([
                'period_days' => 7,
                'stocks' => [[
                    'ticker' => 'AAPL',
                    'buzz_score' => 60.0,
                    'bullish_pct' => 58,
                    'mentions' => 94,
                ]],
            ]),
            'https://api.adanos.org/news/stocks/v1/compare*' => Http::response([
                'period_days' => 7,
                'stocks' => [[
                    'ticker' => 'AAPL',
                    'buzz_score' => 48.0,
                    'bullish_pct' => 61,
                    'mentions' => 37,
                ]],
            ]),
            'https://api.adanos.org/polymarket/stocks/v1/compare*' => Http::response([
                'period_days' => 7,
                'stocks' => [[
                    'ticker' => 'AAPL',
                    'buzz_score' => 50.0,
                    'bullish_pct' => 64,
                    'trade_count' => 512,
                ]],
            ]),
        ];
    }
}
