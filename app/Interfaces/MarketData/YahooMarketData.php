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

    public function quote(String $symbol): Collection
    {

        $quote = $this->client->getQuote($symbol);

        if (empty($quote)) return collect();

        return collect([
            'name' => $quote->getLongName() ?? $quote->getShortName(),
            'symbol' => $quote->getSymbol(),
            'market_value' => (float) $quote->getRegularMarketPrice(),
            'fifty_two_week_high' => (float) $quote->getFiftyTwoWeekHigh(),
            'fifty_two_week_low' => (float) $quote->getFiftyTwoWeekLow(),
            'forward_pe' => (float) $quote->getForwardPE(),
            'trailing_pe' => (float) $quote->getTrailingPE(),
            'market_cap' => (int) $quote->getMarketCap(),
            'book_value' => (float) $quote->getBookValue(),
            'last_dividend_date' => $quote->getDividendDate(),
            'dividend_yield' => (float) $quote->getTrailingAnnualDividendYield() * 100
        ]);
    }

    public function dividends(String $symbol, $startDate, $endDate): Collection
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

    public function splits(String $symbol, $startDate, $endDate): Collection
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

    public function history(String $symbol, $startDate, $endDate): Collection
    {

        return collect($this->client->getHistoricalQuoteData($symbol, ApiClient::INTERVAL_1_DAY, $startDate, $endDate))
            ->mapWithKeys(function($history) use ($symbol) {

                $date = $history->getDate()->format('Y-m-d');

                return [ $date => [
                        'symbol' => $symbol,
                        'date' => $date,
                        'close' => (float) $history->getClose(),
                    ]];
            });
    }
}