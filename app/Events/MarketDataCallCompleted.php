<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

class MarketDataCallCompleted
{
    use Dispatchable;

    /**
     * Fired after each attempt to call a market data provider.
     *
     * @param  int  $attempt  Zero-based position of the provider in the fallback chain
     */
    public function __construct(
        public string $provider,
        public string $method,
        public ?string $symbol,
        public float $durationMs,
        public bool $successful,
        public int $attempt = 0,
        public ?string $error = null,
    ) {}
}
