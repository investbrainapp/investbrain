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

        return new Quote([
            'name' => $symbol,
            'symbol' => $symbol,
            'currency' => Arr::get($fundamental, 'symbols.0.quoteAsset'),
            'market_value' => (float) Arr::get($quote, 'weightedAvgPrice'),
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
        $endDate = Carbon::parse($endDate);

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
                    'startTime' => $startDate->timestamp * 1000,
                    'endTime' => $chunkEnd->timestamp * 1000,
                ])->get('klines');

            $history = $response->json();

            throw_if(empty($history), NotFoundHttpException::class, "Symbol `{$symbol}` was not found");

            $chunkedHistory = collect($history)
                ->mapWithKeys(function ($history_item) use ($symbol) {

                    $date = Carbon::parse($history_item[0])->format('Y-m-d');

                    return [$date => new Ohlc([
                        'symbol' => $symbol,
                        'date' => $date,
                        'close' => $history_item[4],
                    ])];
                });

            $allHistory = $allHistory->merge($chunkedHistory);
        }

        return $allHistory;
    }
}
