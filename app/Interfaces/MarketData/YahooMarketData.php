<?php

namespace App\Interfaces\MarketData;

use Illuminate\Support\Collection;
use Scheb\YahooFinanceApi\ApiClient;
use Scheb\YahooFinanceApi\ApiClientFactory as YahooFinance;

class YahooMarketData implements MarketDataInterface
{
    public ApiClient $client;

    public function __construct() {

        // create yahoo finance client factory
        $this->client = YahooFinance::createApiClient();
    }

    public function exists(String $symbol): Bool
    {

        return $this->quote($symbol)->isNotEmpty();
    }

    public function quote($symbol): Collection
    {

        $quote = $this->client->getQuote($symbol);

        if (empty($quote)) return collect();

        return collect([
            'name' => $quote->getLongName() ?? $quote->getShortName(),
            'symbol' => $quote->getSymbol(),
            'market_value' => $quote->getRegularMarketPrice(),
            'fifty_two_week_high' => $quote->getFiftyTwoWeekHigh(),
            'fifty_two_week_low' => $quote->getFiftyTwoWeekLow(),
            'forward_pe' => $quote->getForwardPE(),
            'trailing_pe' => $quote->getTrailingPE(),
            'market_cap' => $quote->getMarketCap(),
            'book_value' => $quote->getBookValue(),
            'last_dividend_date' => $quote->getDividendDate(),
            'dividend_yield' => $quote->getTrailingAnnualDividendYield()
        ]);
    }

    public function dividends($symbol, $startDate, $endDate): Collection
    {

        return collect($this->client->getHistoricalDividendData($symbol, $startDate, $endDate))
                        ->map(function($dividend) use ($symbol) {
                            
                            return [
                                'symbol' => $symbol,
                                'date' => $dividend->getDate()->format('Y-m-d H:i:s'),
                                'dividend_amount' => $dividend->getDividends(),
                            ];
                        });
    }

    public function splits($symbol, $startDate, $endDate): Collection
    {   

        return collect($this->client->getHistoricalSplitData($symbol, $startDate, $endDate))
                        ->map(function($split) use ($symbol) {
                            $split_amount = explode(':', $split->getStockSplits());

                            return [
                                'symbol' => $symbol,
                                'date' => $split->getDate()->format('Y-m-d H:i:s'),
                                'split_amount' => $split_amount[0] / $split_amount[1],
                            ];
                        });
    }
}