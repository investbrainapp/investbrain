<?php

declare(strict_types=1);

namespace App\Interfaces\MarketData;

use App\Interfaces\MarketData\Types\Quote;
use Illuminate\Support\Collection;

interface MarketDataInterface
{
    /**
     * Does this symbol actually exist?
     */
    public function exists(string $symbol): bool;

    /**
     * Get quote data
     */
    public function quote(string $symbol): Quote;

    /**
     * Get dividend data
     */
    public function dividends(string $symbol, \DateTimeInterface $startDate, \DateTimeInterface $endDate): Collection;

    /**
     * Get split data
     */
    public function splits(string $symbol, \DateTimeInterface $startDate, \DateTimeInterface $endDate): Collection;

    /**
     * Get historical close data
     */
    public function history(string $symbol, \DateTimeInterface $startDate, \DateTimeInterface $endDate): Collection;
}
