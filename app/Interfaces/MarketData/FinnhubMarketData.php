<?php

declare(strict_types=1);

namespace App\Interfaces\MarketData;

use App\Interfaces\MarketData\Types\Dividend;
use App\Interfaces\MarketData\Types\Ohlc;
use App\Interfaces\MarketData\Types\Quote;
use App\Interfaces\MarketData\Types\Split;
use Finnhub\ObjectSerializer;
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

        if (is_null(Arr::get($quote, 'd'))) {
            throw new \Exception('Could not find ticker on Finnhub');
        }

        $fundamental = cache()->remember(
            'fh-symbol-'.$symbol,
            1440,
            function () use ($symbol) {

                return array_merge(
                    (array) ObjectSerializer::sanitizeForSerialization($this->client->companyProfile2($symbol)),
                    (array) ObjectSerializer::sanitizeForSerialization($this->client->companyBasicFinancials($symbol, 'all')),
                );
            }
        );

        return new Quote([
            'name' => Arr::get($fundamental, 'name'),
            'symbol' => $symbol,
            'currency' => Arr::get($fundamental, 'currency'),
            'market_value' => Arr::get($quote, 'c'),
            'fifty_two_week_high' => Arr::get($fundamental, 'metric.52WeekHigh'),
            'fifty_two_week_low' => Arr::get($fundamental, 'metric.52WeekLow'),
            'forward_pe' => Arr::get($fundamental, 'metric.peAnnual'),
            'trailing_pe' => Arr::get($fundamental, 'metric.peTTM'),
            'market_cap' => Arr::get($fundamental, 'metric.marketCapitalization', 0) * 1000000,
            'book_value' => Arr::get($fundamental, 'metric.bookValuePerShareAnnual'),
            'dividend_yield' => Arr::get($fundamental, 'metric.dividendYieldIndicatedAnnual'),
            'meta_data' => [
                'country' => Arr::get($fundamental, 'country'),
                'exchange' => Arr::get($fundamental, 'exchange'),
                'first_trade_year' => Arr::get($fundamental, 'ipo') ? Carbon::parse(Arr::get($fundamental, 'ipo'))->format('Y') : null,
                'source' => 'finnhub',
            ],
        ]);
    }

    public function dividends($symbol, $startDate, $endDate): Collection
    {
        $dividends = $this->client->stockDividends($symbol, $startDate->toDateString(), $endDate->toDateString());

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

        $splits = $this->client->stockSplits($symbol, $startDate->toDateString(), $endDate->toDateString());

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
            $date = Carbon::createFromTimestamp($timestamp)->toDateString();

            return [$date => new Ohlc([
                'symbol' => $symbol,
                'date' => $date,
                'close' => $closes[$index],
            ])];
        });
    }
}
