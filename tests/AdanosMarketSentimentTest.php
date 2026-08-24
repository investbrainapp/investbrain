<?php

declare(strict_types=1);

namespace Tests;

use App\Interfaces\MarketSentiment\AdanosMarketSentiment;
use App\Interfaces\MarketSentiment\MarketSentimentInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;

class AdanosMarketSentimentTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        Carbon::setTestNow('2026-08-24 12:00:00 UTC');
        config()->set('adanos.key', 'test-key');
        config()->set('investbrain.sentiment_provider', 'adanos');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_it_normalizes_available_sentiment_sources(): void
    {
        Http::fake([
            '*/reddit/*' => Http::response(['stocks' => [[
                'ticker' => 'AAPL',
                'buzz_score' => 72.4,
                'bullish_pct' => 61,
                'mentions' => 120,
            ]]]),
            '*/x/*' => Http::response([], 503),
            '*/news/*' => Http::response(['stocks' => [[
                'ticker' => 'AAPL',
                'buzz_score' => 48,
                'bullish_pct' => 54,
                'mentions' => 37,
            ]]]),
            '*/polymarket/*' => Http::response(['stocks' => [[
                'ticker' => 'AAPL',
                'buzz_score' => 31.2,
                'bullish_pct' => 58,
                'trade_count' => 512,
            ]]]),
        ]);

        $sentiment = app(MarketSentimentInterface::class)->sentiment('aapl');

        $this->assertSame(['reddit', 'news', 'polymarket'], $sentiment->keys()->all());
        $this->assertSame(72.4, $sentiment['reddit']['buzz_score']);
        $this->assertSame(120, $sentiment['reddit']['activity']);
        $this->assertSame('trades', $sentiment['polymarket']['activity_label']);

        Http::assertSentCount(4);
        Http::assertSent(fn ($request) => $request->hasHeader('X-API-Key', 'test-key')
            && $request['tickers'] === 'AAPL'
            && $request['from'] === '2026-08-18'
            && $request['to'] === '2026-08-24'
            && ! isset($request['days']));
    }

    public function test_it_reuses_the_cached_result(): void
    {
        Http::fake(['*' => Http::response(['stocks' => [['ticker' => 'AAPL']]])]);

        $provider = app(AdanosMarketSentiment::class);
        $provider->sentiment('AAPL');
        $provider->sentiment('AAPL');

        Http::assertSentCount(4);
    }

    public function test_it_fails_when_no_source_returns_sentiment(): void
    {
        Http::fake(['*' => Http::response([], 503)]);

        $this->expectException(RuntimeException::class);

        app(AdanosMarketSentiment::class)->sentiment('AAPL');
    }

    public function test_it_rejects_invalid_symbols_before_requesting_data(): void
    {
        Http::fake();
        $rejected = false;

        try {
            app(AdanosMarketSentiment::class)->sentiment('AAPL,MSFT');
        } catch (InvalidArgumentException) {
            $rejected = true;
        }

        $this->assertTrue($rejected);
        Http::assertNothingSent();
    }
}
