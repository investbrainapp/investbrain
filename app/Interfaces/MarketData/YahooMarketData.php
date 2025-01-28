<?php

namespace App\Interfaces\MarketData;

use Illuminate\Support\Collection;
use Scheb\YahooFinanceApi\ApiClient;
use App\Interfaces\MarketData\Types\Ohlc;
use App\Interfaces\MarketData\Types\Quote;
use App\Interfaces\MarketData\Types\Split;
use App\Interfaces\MarketData\Types\Dividend;
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

    public function quote(String $symbol): Quote
    {

        $quote = $this->client->getQuote($symbol);

        if (empty($quote)) return collect();

        return new Quote([
            'name' => $quote->getLongName() ?? $quote->getShortName(),
            'symbol' => $symbol,
            'market_value' => $quote->getRegularMarketPrice(),
            'fifty_two_week_high' => $quote->getFiftyTwoWeekHigh(),
            'fifty_two_week_low' => $quote->getFiftyTwoWeekLow(),
            'forward_pe' => $quote->getForwardPE(),
            'trailing_pe' => $quote->getTrailingPE(),
            'market_cap' => $quote->getMarketCap(),
            'book_value' => $quote->getBookValue(),
            'last_dividend_date' => $quote->getDividendDate(),
            'dividend_yield' => $quote->getTrailingAnnualDividendYield() * 100
        ]);
    }

    public function dividends(String $symbol, $startDate, $endDate): Collection
    {

        return collect($this->client->getHistoricalDividendData($symbol, $startDate, $endDate))
                        ->map(function($dividend) use ($symbol) {
                            
                            return new Dividend([
                                'symbol' => $symbol,
                                'date' => $dividend->getDate(),
                                'dividend_amount' => $dividend->getDividends(),
                            ]);
                        });
    }

    public function splits(String $symbol, $startDate, $endDate): Collection
    {   

        return collect($this->client->getHistoricalSplitData($symbol, $startDate, $endDate))
                        ->map(function($split) use ($symbol) {
                            $split_amount = explode(':', $split->getStockSplits());

                            return new Split([
                                'symbol' => $symbol,
                                'date' => $split->getDate(),
                                'split_amount' => $split_amount[0] / $split_amount[1],
                            ]);
                        });
    }

    public function history(String $symbol, $startDate, $endDate): Collection
    {

        return collect($this->client->getHistoricalQuoteData($symbol, ApiClient::INTERVAL_1_DAY, $startDate, $endDate))
            ->mapWithKeys(function($history) use ($symbol) {

                $date = $history->getDate()->format('Y-m-d');

                return [ $date => new Ohlc([
                        'symbol' => $symbol,
                        'date' => $date,
                        'close' => $history->getClose(),
                    ]) ];
            });
    }
}