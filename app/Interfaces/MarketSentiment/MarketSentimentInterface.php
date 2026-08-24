<?php

declare(strict_types=1);

namespace App\Interfaces\MarketSentiment;

use Illuminate\Support\Collection;

interface MarketSentimentInterface
{
    /**
     * Get sentiment metrics keyed by source for a symbol.
     */
    public function sentiment(string $symbol): Collection;
}
