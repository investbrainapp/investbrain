<?php

declare(strict_types=1);

namespace App\Interfaces\MarketData;

use App\Interfaces\MarketData\Types\Dividend;
use App\Interfaces\MarketData\Types\Ohlc;
use App\Interfaces\MarketData\Types\Quote;
use App\Interfaces\MarketData\Types\Split;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class TwelveDataMarketData implements MarketDataInterface
{
    public PendingRequest $client;

    public string $apiBaseUrl = 'https://api.twelvedata.com/';

    public function __construct()
    {
        $this->createNewClient();
    }

    private function createNewClient()
    {
        $this->client = Http::withOptions([
            'headers' => [
                'content-type' => 'application/json',
                'accept' => 'application/json',
            ],
        ])->withQueryParameters([
            'apikey' => config('twelvedata.secret'),
        ]);
    }

    public function exists(string $symbol): bool
    {

        return (bool) $this->quote($symbol);
    }

    public function quote(string $symbol): Quote
    {

        $response = $this->client
            ->baseUrl($this->apiBaseUrl)
            ->withQueryParameters(['symbol' => $symbol])
            ->get('price');

        $quote = $response->json();

        if (! isset($quote['price'])) {
            throw new \Exception('Could not find ticker on Twelve Data');
        }

        $current_market_value = Arr::get($quote, 'price');

        $fundamental = cache()->remember(
            'twelve-data-symbol-'.$symbol,
            1440,
            function () use ($symbol) {

                $this->createNewClient();

                $response = $this->client
                    ->baseUrl($this->apiBaseUrl)
                    ->withQueryParameters(['symbol' => $symbol])
                    ->get('quote');

                return $response->json();
            }
        );

        return new Quote([
            'name' => Arr::get($fundamental, 'name'),
            'symbol' => $symbol,
            'currency' => Arr::get($fundamental, 'currency'),
            'market_value' => (float) $current_market_value,
            'fifty_two_week_high' => (float) Arr::get($fundamental, 'fifty_two_week.high'),
            'fifty_two_week_low' => (float) Arr::get($fundamental, 'fifty_two_week.low'),
            'meta_data' => [
                'exchange' => Arr::get($fundamental, 'exchange'),
                'source' => 'twelvedata',
            ],
        ]);
    }

    public function dividends(string $symbol, $startDate, $endDate): Collection
    {

        $response = $this->client
            ->baseUrl($this->apiBaseUrl)
            ->withQueryParameters([
                'symbol' => $symbol,
                'start_date' => Carbon::parse($startDate)->toDateString(),
                'end_date' => Carbon::parse($endDate)->toDateString(),
            ])
            ->get('dividends');

        $dividends = $response->json('dividends');

        return collect($dividends)
            ->map(function ($dividend) use ($symbol) {

                return new Dividend([
                    'symbol' => $symbol,
                    'date' => Arr::get($dividend, 'ex_date'),
                    'dividend_amount' => Arr::get($dividend, 'amount'),
                ]);
            });
    }

    public function splits(string $symbol, $startDate, $endDate): Collection
    {

        $response = $this->client
            ->baseUrl($this->apiBaseUrl)
            ->withQueryParameters([
                'symbol' => $symbol,
                'start_date' => Carbon::parse($startDate)->toDateString(),
                'end_date' => Carbon::parse($endDate)->toDateString(),
            ])
            ->get('splits');

        $splits = $response->json('splits');

        return collect($splits)
            ->map(function ($split) use ($symbol) {

                return new Split([
                    'symbol' => $symbol,
                    'date' => Arr::get($split, 'date'),
                    'split_amount' => Arr::get($split, 'from_factor') / Arr::get($split, 'to_factor'),
                ]);
            });
    }

    public function history(string $symbol, $startDate, $endDate): Collection
    {

        $response = $this->client
            ->baseUrl($this->apiBaseUrl)
            ->withQueryParameters([
                'symbol' => $symbol,
                'interval' => '1day',
                'start_date' => Carbon::parse($startDate)->toDateString(),
                'end_date' => Carbon::parse($endDate)->toDateString(),
            ])
            ->get('time_series');

        $values = $response->json('values');

        return collect($values)
            ->mapWithKeys(function ($history) use ($symbol) {

                $date = Carbon::parse(Arr::get($history, 'datetime'))->toDateString();

                return [$date => new Ohlc([
                    'symbol' => $symbol,
                    'date' => $date,
                    'close' => (float) Arr::get($history, 'close'),
                ])];
            });
    }
}
