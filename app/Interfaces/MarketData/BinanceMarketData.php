<?php

declare(strict_types=1);

namespace App\Interfaces\MarketData;

use App\Interfaces\MarketData\Types\Ohlc;
use App\Interfaces\MarketData\Types\Quote;
use Carbon\CarbonInterval;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class BinanceMarketData implements MarketDataInterface
{
    public PendingRequest $client;

    public string $apiBaseUrl = 'https://data-api.binance.vision/api/v3/';

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
            ->withQueryParameters([
                'symbol' => $symbol,
            ])->get('ticker');

        $quote = $response->json();

        throw_if(empty(Arr::get($quote, 'weightedAvgPrice')), NotFoundHttpException::class, "Symbol `{$symbol}` was not found");

        $fundamental = cache()->remember(
            'binance-fdmtl-'.$symbol,
            1440,
            function () use ($symbol) {

                $this->createNewClient();

                $response = $this->client
                    ->baseUrl($this->apiBaseUrl)
                    ->withQueryParameters(['symbol' => $symbol])
                    ->get('exchangeInfo');

                return $response->json();
            }
        );

        $yearLow = cache()->remember(
            'binance-low-'.$symbol,
            1440,
            function () use ($symbol) {

                $this->createNewClient();

                $response = $this->client
                    ->baseUrl($this->apiBaseUrl)
                    ->withQueryParameters([
                        'symbol' => $symbol,
                        'interval' => '1d',
                        'start' => now()->firstOfYear()->timestamp,
                    ])
                    ->get('klines');

                return $response->json();
            }
        );

        return new Quote([
            'name' => $symbol,
            'symbol' => $symbol,
            'currency' => Arr::get($fundamental, 'symbols.quoteAsset'),
            'market_value' => Arr::get($quote, 'weightedAvgPrice'),
        ]);
    }

    public function dividends(string $symbol, $startDate, $endDate): Collection
    {
        // noop
        return collect();
    }

    public function splits(string $symbol, $startDate, $endDate): Collection
    {
        // noop
        return collect();
    }

    public function history(string $symbol, $startDate, $endDate): Collection
    {
        $startDate = Carbon::parse($startDate);
        $endDate = Carbon::parse($endDate); // alpaca has sip data limits

        $allHistory = collect();

        $chunks = 500;

        $period = CarbonInterval::days($chunks)->toPeriod($startDate, $endDate);
        foreach ($period as $startDate) {

            $chunkEnd = $startDate->copy()->addDays($chunks - 1);

            if ($chunkEnd->gt($endDate)) {
                $chunkEnd = $endDate;
            }

            $this->createNewClient();

            $response = $this->client
                ->baseUrl($this->apiBaseUrl)
                ->withQueryParameters([
                    'symbol' => $symbol,
                    'interval' => '1d',
                    'start' => $startDate->timestamp,
                    'end' => $chunkEnd->timestamp,
                ])->get('klines');

            $history = $response->json();

            throw_if(empty($history), NotFoundHttpException::class, "Symbol `{$symbol}` was not found");

            $chunkedHistory = collect($history)
                ->mapWithKeys(function ($history) use ($symbol) {

                    $date = Carbon::parse($history[0])->format('Y-m-d');

                    return [$date => new Ohlc([
                        'symbol' => $symbol,
                        'date' => $date,
                        'close' => $history[4],
                    ])];
                });

            $allHistory = $allHistory->merge($chunkedHistory);
        }

        return $allHistory;
    }
}
