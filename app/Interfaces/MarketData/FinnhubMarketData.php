<?php

declare(strict_types=1);

namespace App\Interfaces\MarketData;

use App\Interfaces\MarketData\Types\Dividend;
use App\Interfaces\MarketData\Types\Ohlc;
use App\Interfaces\MarketData\Types\Quote;
use App\Interfaces\MarketData\Types\Split;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class FinnhubMarketData implements MarketDataInterface
{
    public \Finnhub\Api\DefaultApi $client;

    public function __construct()
    {

        $this->client = new \Finnhub\Api\DefaultApi(
            new \GuzzleHttp\Client,
            \Finnhub\Configuration::getDefaultConfiguration()->setApiKey('token', config('finnhub.key'))
        );
    }

    public function exists(string $symbol): bool
    {

        return (bool) $this->quote($symbol);
    }

    public function quote(string $symbol): Quote
    {
        $quote = $this->client->quote($symbol);

        $fundamental = cache()->remember(
            'fh-symbol-'.$symbol,
            1440,
            function () use ($symbol) {
                return $this->client->companyBasicFinancials($symbol, 'all');
            }
        );

        return new Quote([
            'name' => Arr::get($fundamental, 'metric.name'),
            'symbol' => $symbol,
            'market_value' => Arr::get($quote, 'c'),
            'fifty_two_week_high' => Arr::get($fundamental, 'metric.52WeekHigh'),
            'fifty_two_week_low' => Arr::get($fundamental, 'metric.52WeekLow'),
            'forward_pe' => Arr::get($fundamental, 'metric.forwardPE'), // confirm
            'trailing_pe' => Arr::get($fundamental, 'metric.trailingPE'), // confirm
            'market_cap' => Arr::get($fundamental, 'metric.marketCapitalization'), // confirm
            'book_value' => Arr::get($fundamental, 'metric.bookValuePerShare'), // confirm
            'last_dividend_date' => Arr::get($fundamental, 'metric.lastDivDate'), // confirm
            'dividend_yield' => Arr::get($fundamental, 'metric.dividendYield'), // confirm
        ]);
    }

    public function dividends($symbol, $startDate, $endDate): Collection
    {
        $dividends = $this->client->stockDividends($symbol, $startDate->format('Y-m-d'), $endDate->format('Y-m-d'));

        return collect($dividends)->map(function ($dividend) use ($symbol) {

            return new Dividend([
                'symbol' => $symbol,
                'date' => Carbon::parse(Arr::get($dividend, 'date')),
                'dividend_amount' => Arr::get($dividend, 'amount'),
            ]);
        });
    }

    public function splits($symbol, $startDate, $endDate): Collection
    {

        $splits = $this->client->stockSplits($symbol, $startDate->format('Y-m-d'), $endDate->format('Y-m-d'));

        return collect($splits)->map(function ($split) use ($symbol) {

            return new Split([
                'symbol' => $symbol,
                'date' => Carbon::parse(Arr::get($split, 'date')),
                'split_amount' => Arr::get($split, 'toFactor') / Arr::get($split, 'fromFactor'),
            ]);
        });
    }

    public function history($symbol, $startDate, $endDate): Collection
    {

        $history = $this->client->stockCandles($symbol, 'D', $startDate->timestamp, $endDate->timestamp);

        $timestamps = Arr::get($history, 't', []);
        $closes = Arr::get($history, 'c', []);

        return collect($timestamps)->mapWithKeys(function ($timestamp, $index) use ($symbol, $closes) {
            $date = Carbon::createFromTimestamp($timestamp)->format('Y-m-d');

            return [$date => new Ohlc([
                'symbol' => $symbol,
                'date' => $date,
                'close' => $closes[$index],
            ])];
        });
    }
}
