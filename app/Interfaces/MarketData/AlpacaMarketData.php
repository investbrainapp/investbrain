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

class AlpacaMarketData implements MarketDataInterface
{
    public PendingRequest $client;

    public string $dataBaseUrl = 'https://data.alpaca.markets/';

    public string $apiBaseUrl = 'https://api.alpaca.markets/';

    public function __construct()
    {
        $this->client = Http::withOptions([
            'headers' => [
                'content-type' => 'application/json',
                'accept' => 'application/json',
                'Apca-Api-Key-Id' => config('alpaca.key'),
                'Apca-Api-Secret-Key' => config('alpaca.secret'),
            ],
        ]);
    }

    public function exists(string $symbol): bool
    {
        return (bool) $this->quote($symbol);
    }

    public function quote(string $symbol): Quote
    {
        $response = $this->client->baseUrl($this->dataBaseUrl)->get("v2/stocks/{$symbol}/trades/latest");

        $quote = $response->json('trade');

        $fundamental = cache()->remember(
            'ap-symbol-'.$symbol,
            1440,
            function () use ($symbol) {

                $basic = $this->client->baseUrl($this->apiBaseUrl)->get("v2/assets/{$symbol}")->json();
                $fifty_two_week = $this->client->baseUrl($this->dataBaseUrl)->withQueryParameters([
                    'timeframe' => '12M',
                    'start' => now()->subWeeks(53)->format('Y-m-d'),
                    'end' => now()->subWeeks(1)->format('Y-m-d'), // todo: can't query recent SIP data
                ])->get("v2/stocks/{$symbol}/bars")->json();

                return array_merge($fifty_two_week, $basic);
            }
        );

        return new Quote([
            'name' => Arr::get($fundamental, 'name'),
            'symbol' => $symbol,
            'currency' => 'USD', // Alpaca only has US equitities
            'market_value' => Arr::get($quote, 'p'),
            'fifty_two_week_high' => Arr::get($fundamental, 'bars.0.h'),
            'fifty_two_week_low' => Arr::get($fundamental, 'bars.0.l'),
        ]);
    }

    public function dividends(string $symbol, $startDate, $endDate): Collection
    {
        $response = $this->client->baseUrl($this->dataBaseUrl)->withQueryParameters([
            'symbols' => $symbol,
            'limit' => 1000,
            'sort' => 'asc',
            'types' => 'cash_dividend',
            'start' => $startDate->format('Y-m-d'),
            'end' => $endDate->format('Y-m-d'),
        ])->get('v1/corporate-actions');

        $dividends = $response->json('corporate_actions.cash_dividends');

        return collect($dividends)
            ->map(function ($dividend) use ($symbol) {

                return new Dividend([
                    'symbol' => $symbol,
                    'date' => Carbon::parse(Arr::get($dividend, 'ex_date')),
                    'dividend_amount' => Arr::get($dividend, 'rate'),
                ]);
            });
    }

    public function splits(string $symbol, $startDate, $endDate): Collection
    {
        $response = $this->client->baseUrl($this->dataBaseUrl)->withQueryParameters([
            'symbols' => $symbol,
            'limit' => 1000,
            'sort' => 'asc',
            'types' => 'forward_split',
            'start' => $startDate->format('Y-m-d'),
            'end' => $endDate->format('Y-m-d'),
        ])->get('v1/corporate-actions');

        $splits = $response->json('corporate_actions.forward_splits');

        return collect($splits)
            ->map(function ($split) use ($symbol) {

                return new Split([
                    'symbol' => $symbol,
                    'date' => Carbon::parse(Arr::get($split, 'ex_date')),
                    'split_amount' => Arr::get($split, 'new_rate') / Arr::get($split, 'old_rate'),
                ]);
            });
    }

    public function history(string $symbol, $startDate, $endDate): Collection
    {
        $response = $this->client->baseUrl($this->dataBaseUrl)->withQueryParameters([
            'timeframe' => '1D',
            'start' => Carbon::parse($startDate)->format('Y-m-d'),
            'end' => Carbon::parse($endDate)->subHours(36)->format('Y-m-d'), // todo: can't query recent SIP data
        ])->get("v2/stocks/{$symbol}/bars");

        $history = $response->json('bars');

        return collect($history)
            ->map(function ($history) use ($symbol) {

                $date = Carbon::parse($history['t'])->format('Y-m-d');

                return [$date => new Ohlc([
                    'symbol' => $symbol,
                    'date' => $date,
                    'close' => Arr::get($history, 'c'),
                ])];
            });
    }
}
