<?php

declare(strict_types=1);

namespace Tests;

use App\Interfaces\MarketData\AlphaVantageMarketData;
use App\Interfaces\MarketData\FallbackInterface;
use App\Interfaces\MarketData\Types\Quote;
use App\Interfaces\MarketData\YahooMarketData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Mockery;

class FallbackInterfaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Log::spy();
    }

    public function test_fallback_to_next_provider_on_failure()
    {
        config()->set('investbrain.provider', 'yahoo,alphavantage');
        config()->set('investbrain.interfaces', [
            'yahoo' => YahooMarketData::class,
            'alphavantage' => AlphaVantageMarketData::class,
        ]);

        $yahooMock = Mockery::mock(YahooMarketData::class);
        $yahooMock->shouldReceive('quote')
            ->andThrow(new \Exception('Yahoo failed'));

        $alphaMock = Mockery::mock(AlphaVantageMarketData::class);
        $alphaMock->shouldReceive('quote')
            ->andReturn(new Quote([
                'name' => 'Test Quote',
                'symbol' => 'ACME',
                'currency' => 'USD',
                'market_value' => 10,
            ]));

        $this->app->instance(YahooMarketData::class, $yahooMock);
        $this->app->instance(AlphaVantageMarketData::class, $alphaMock);

        $fallbackInterface = new FallbackInterface;

        $result = $fallbackInterface->quote('ACME');

        $this->assertEquals(new Quote([
            'name' => 'Test Quote',
            'symbol' => 'ACME',
            'currency' => 'USD',
            'market_value' => 10,
        ]), $result);

        Log::shouldHaveReceived('error')->with('Failed calling method quote for ACME (yahoo): Yahoo failed');
    }

    public function test_all_providers_fail()
    {
        config()->set('investbrain.provider', 'yahoo,alpha');
        config()->set('investbrain.interfaces', [
            'yahoo' => YahooMarketData::class,
            'alphavantage' => AlphaVantageMarketData::class,
        ]);

        $yahooMock = Mockery::mock(YahooMarketData::class);
        $yahooMock->shouldReceive('quote')
            ->andThrow(new \Exception('Yahoo failed'));

        $alphaMock = Mockery::mock(AlphaVantageMarketData::class);
        $alphaMock->shouldReceive('quote')
            ->andThrow(new \Exception('Alpha failed'));

        $this->app->instance(YahooMarketData::class, $yahooMock);
        $this->app->instance(AlphaVantageMarketData::class, $alphaMock);

        $fallbackInterface = new FallbackInterface;

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Could not get market data calling method quote: Provider [alpha] is not a valid market data interface.');

        $fallbackInterface->quote('AAPL');

        Log::shouldHaveReceived('error')->with('Failed calling method quote for AAPL (yahoo): Yahoo failed');
        Log::shouldHaveReceived('error')->with('Failed calling method quote for AAPL (alpha): Alpha failed');
    }

    public function test_exists_method_fails_without_exception()
    {
        config()->set('investbrain.provider', 'yahoo,alpha');
        config()->set('investbrain.interfaces', [
            'yahoo' => YahooMarketData::class,
            'alphavantage' => AlphaVantageMarketData::class,
        ]);

        $yahooMock = Mockery::mock(YahooMarketData::class);
        $yahooMock->shouldReceive('exists')
            ->andThrow(new \Exception('Yahoo failed'));

        $alphaMock = Mockery::mock(AlphaVantageMarketData::class);
        $alphaMock->shouldReceive('exists')
            ->andThrow(new \Exception('Alpha failed'));

        $this->app->instance(YahooMarketData::class, $yahooMock);
        $this->app->instance(AlphaVantageMarketData::class, $alphaMock);

        $fallbackInterface = new FallbackInterface;

        $result = $fallbackInterface->exists('ZZZ');

        $this->assertIsBool($result);
        $this->assertFalse($result);
    }
}
