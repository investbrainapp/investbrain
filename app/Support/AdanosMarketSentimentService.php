<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class AdanosMarketSentimentService
{
    private const BASE_URL = 'https://api.adanos.org';

    private const SUPPORTED_SOURCES = [
        'reddit',
        'x',
        'news',
        'polymarket',
    ];

    public function enabled(): bool
    {
        return filled(config('services.adanos.key'));
    }

    public function fetch(string $symbol): ?array
    {
        if (! $this->enabled()) {
            return null;
        }

        $ticker = strtoupper(trim($symbol));

        if ($ticker === '') {
            return null;
        }

        $payload = [];

        foreach ($this->sources() as $source) {
            $row = $this->fetchSourceRow($source, $ticker);

            if ($row !== null) {
                $payload[$source] = $row;
            }
        }

        if ($payload === []) {
            return null;
        }

        $availableRows = collect($payload);
        $availableBuzz = $availableRows
            ->pluck('buzz_score')
            ->filter(static fn ($value) => $value !== null)
            ->map(static fn ($value) => (float) $value)
            ->values();
        $availableBullish = $availableRows
            ->pluck('bullish_pct')
            ->filter(static fn ($value) => $value !== null)
            ->map(static fn ($value) => (float) $value)
            ->values();

        return [
            'symbol' => $ticker,
            'average_buzz' => $availableBuzz->isNotEmpty() ? round($availableBuzz->avg(), 2) : null,
            'average_bullish_pct' => $availableBullish->isNotEmpty() ? round($availableBullish->avg(), 2) : null,
            'coverage' => $availableRows->count(),
            'source_alignment' => $this->determineAlignment($availableBullish),
            'reddit_buzz' => $this->numericValue(Arr::get($payload, 'reddit.buzz_score')),
            'reddit_bullish_pct' => $this->integerValue(Arr::get($payload, 'reddit.bullish_pct')),
            'reddit_mentions' => $this->integerValue(Arr::get($payload, 'reddit.mentions')),
            'x_buzz' => $this->numericValue(Arr::get($payload, 'x.buzz_score')),
            'x_bullish_pct' => $this->integerValue(Arr::get($payload, 'x.bullish_pct')),
            'x_mentions' => $this->integerValue(Arr::get($payload, 'x.mentions')),
            'news_buzz' => $this->numericValue(Arr::get($payload, 'news.buzz_score')),
            'news_bullish_pct' => $this->integerValue(Arr::get($payload, 'news.bullish_pct')),
            'news_mentions' => $this->integerValue(Arr::get($payload, 'news.mentions')),
            'polymarket_buzz' => $this->numericValue(Arr::get($payload, 'polymarket.buzz_score')),
            'polymarket_bullish_pct' => $this->integerValue(Arr::get($payload, 'polymarket.bullish_pct')),
            'polymarket_trade_count' => $this->integerValue(Arr::get($payload, 'polymarket.trade_count')),
            'payload' => $payload,
        ];
    }

    protected function fetchSourceRow(string $source, string $symbol): ?array
    {
        $response = Http::acceptJson()
            ->baseUrl(self::BASE_URL)
            ->timeout(10)
            ->withHeader('X-API-Key', (string) config('services.adanos.key'))
            ->get(sprintf('/%s/stocks/v1/compare', $source), [
                'tickers' => $symbol,
                'days' => $this->days(),
            ]);

        if (! $response->successful()) {
            return null;
        }

        $stocks = $response->json('stocks');

        if (! is_array($stocks) || $stocks === []) {
            return null;
        }

        $row = collect($stocks)->first(function ($item) use ($symbol) {
            return is_array($item) && strtoupper((string) Arr::get($item, 'ticker')) === $symbol;
        });

        if (! is_array($row)) {
            $row = is_array($stocks[0] ?? null) ? $stocks[0] : null;
        }

        return is_array($row) ? $row : null;
    }

    protected function determineAlignment(Collection $bullishValues): string
    {
        if ($bullishValues->isEmpty()) {
            return 'unavailable';
        }

        if ($bullishValues->count() === 1) {
            return 'single-source';
        }

        $spread = $bullishValues->max() - $bullishValues->min();

        if ($spread <= 15) {
            return 'aligned';
        }

        if ($spread <= 35) {
            return 'mixed';
        }

        return 'divergent';
    }

    protected function numericValue(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return round((float) $value, 2);
    }

    protected function integerValue(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) round((float) $value);
    }

    protected function days(): int
    {
        return max(1, min(30, (int) config('services.adanos.market_sentiment_days', 7)));
    }

    /**
     * @return array<int, string>
     */
    protected function sources(): array
    {
        $configured = array_filter(array_map('trim', explode(',', (string) config('services.adanos.market_sentiment_sources', 'reddit,x,news,polymarket'))));
        $filtered = array_values(array_intersect(self::SUPPORTED_SOURCES, $configured));

        return $filtered !== [] ? $filtered : self::SUPPORTED_SOURCES;
    }
}
