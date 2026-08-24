<?php

declare(strict_types=1);

namespace App\Interfaces\MarketSentiment;

use Illuminate\Http\Client\Pool;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;

class AdanosMarketSentiment implements MarketSentimentInterface
{
    private const SOURCES = ['reddit', 'x', 'news', 'polymarket'];

    public function sentiment(string $symbol): Collection
    {
        $symbol = strtoupper(trim($symbol));

        if (! preg_match('/^[A-Z0-9.^-]{1,20}$/', $symbol)) {
            throw new InvalidArgumentException("Invalid market sentiment symbol [{$symbol}].");
        }

        if (blank(config('adanos.key'))) {
            throw new RuntimeException('ADANOS_API_KEY is not configured.');
        }

        return cache()->remember(
            "market-sentiment:adanos:{$symbol}",
            now()->addMinutes(30),
            function () use ($symbol): Collection {
                $sentiment = $this->fetch($symbol)
                    ->filter();

                throw_if($sentiment->isEmpty(), RuntimeException::class, "Adanos returned no sentiment for {$symbol}.");

                return $sentiment;
            }
        );
    }

    private function fetch(string $symbol): Collection
    {
        $to = Carbon::now('UTC')->toDateString();
        $from = Carbon::now('UTC')->subDays(6)->toDateString();

        $responses = Http::pool(fn (Pool $pool): array => collect(self::SOURCES)
            ->mapWithKeys(fn (string $source): array => [$source => $pool
                ->as($source)
                ->acceptJson()
                ->withHeader('X-API-Key', config('adanos.key'))
                ->timeout(10)
                ->get("https://api.adanos.org/{$source}/stocks/v1/compare", [
                    'tickers' => $symbol,
                    'from' => $from,
                    'to' => $to,
                ])])
            ->all());

        return collect($responses)->mapWithKeys(function ($response, string $source) use ($symbol): array {
            if (! $response->successful()) {
                return [];
            }

            $row = collect($response->json('stocks', []))->firstWhere('ticker', $symbol);

            if (! is_array($row)) {
                return [];
            }

            $activityKey = $source === 'polymarket' ? 'trade_count' : 'mentions';

            return [$source => [
                'buzz_score' => (float) Arr::get($row, 'buzz_score', 0),
                'bullish_pct' => (int) Arr::get($row, 'bullish_pct', 0),
                'activity' => (int) Arr::get($row, $activityKey, 0),
                'activity_label' => $source === 'polymarket' ? 'trades' : 'mentions',
            ]];
        });
    }
}
