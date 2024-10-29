<?php 

namespace Tests;

use Mockery;
use Tests\TestCase;
use Illuminate\Support\Facades\Log;
use App\Interfaces\MarketData\YahooMarketData;
use App\Interfaces\MarketData\FallbackInterface;
use App\Interfaces\MarketData\AlphaVantageMarketData;
use App\Interfaces\MarketData\Types\Quote;

class FallbackInterfaceTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        Log::spy();
    }

    public function testFallbackToNextProviderOnFailure()
    {
        config()->set('investbrain.provider', 'yahoo,alphavantage');
        config()->set('investbrain.interfaces', [
            'yahoo' => YahooMarketData::class,
            'alphavantage' => AlphaVantageMarketData::class,
        ]);

        $yahooMock = Mockery::mock(YahooMarketData::class);
        $yahooMock->shouldReceive('quote')
                  ->andThrow(new \Exception("Yahoo failed"));

        $alphaMock = Mockery::mock(AlphaVantageMarketData::class);
        $alphaMock->shouldReceive('quote')
                  ->andReturn(new Quote(['market_value' => 10]));

        $this->app->instance(YahooMarketData::class, $yahooMock);
        $this->app->instance(AlphaVantageMarketData::class, $alphaMock);

        $fallbackInterface = new FallbackInterface();

        $result = $fallbackInterface->quote('ACME');

        $this->assertEquals(new Quote(['market_value' => 10]), $result);

        Log::shouldHaveReceived('warning')->with('Failed calling method quote (yahoo): Yahoo failed');
    }

    public function testAllProvidersFail()
    {
        config()->set('investbrain.provider', 'yahoo,alpha');
        config()->set('investbrain.interfaces', [
            'yahoo' => YahooMarketData::class,
            'alphavantage' => AlphaVantageMarketData::class,
        ]);

        $yahooMock = Mockery::mock(YahooMarketData::class);
        $yahooMock->shouldReceive('quote')
                  ->andThrow(new \Exception("Yahoo failed"));

        $alphaMock = Mockery::mock(AlphaVantageMarketData::class);
        $alphaMock->shouldReceive('quote')
                  ->andThrow(new \Exception("Alpha failed"));

        $this->app->instance(YahooMarketData::class, $yahooMock);
        $this->app->instance(AlphaVantageMarketData::class, $alphaMock);

        $fallbackInterface = new FallbackInterface();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Could not get market data: Provider [alpha] is not a valid market data interface.');

        $fallbackInterface->quote('AAPL');

        Log::shouldHaveReceived('warning')->with('Failed calling method quote (yahoo): Yahoo failed');
        Log::shouldHaveReceived('warning')->with('Failed calling method quote (alpha): Alpha failed');
    }
}