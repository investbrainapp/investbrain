<?php

namespace App\Interfaces\MarketData;

use Illuminate\Support\Collection;

interface MarketDataInterface
{
    /**
     * Does this symbol actually exist?
     * 
     * @param String $symbol
     * 
     * @return Bool
     */
    public function exists(String $symbol): Bool;

    /**
     * Get quote data
     * 
     * @param String $symbol
     * 
     * @return Collection
     */
    public function quote(String $symbol): Collection;

    /**
     * Get dividend data
     * 
     * @param String $symbol
     * @param \DateTimeInterface $startDate
     * @param \DateTimeInterface $endDate
     * 
     * @return Collection
     */
    public function dividends(String $symbol, \DateTimeInterface $startDate, \DateTimeInterface $endDate): Collection;

    /**
     * Get split data
     * 
     * @param String $symbol
     * @param \DateTimeInterface $startDate
     * @param \DateTimeInterface $endDate
     * 
     * @return Collection
     */
    public function splits(String $symbol, \DateTimeInterface $startDate, \DateTimeInterface $endDate): Collection;
}
