<?php

namespace App\Interfaces\MarketData\Types;

use DateTime;
use Illuminate\Support\Carbon;

class Quote extends MarketDataType
{
    public function setName($name): self
    {
        $this->items['name'] = (string) $name;

        return $this;
    }

    public function getName(): string
    {
        return $this->items['name'] ?? '';
    }

    public function setSymbol($symbol): self
    {
        $this->items['symbol'] = (string) $symbol;

        return $this;
    }

    public function getSymbol(): string
    {
        return $this->items['symbol'] ?? '';
    }

    public function setMarketValue($marketValue): self
    {
        $this->items['market_value'] = (float) $marketValue;

        return $this;
    }

    public function getMarketValue(): float
    {
        return $this->items['market_value'] ?? 0.0;
    }

    public function setFiftyTwoWeekHigh($high): self
    {
        $this->items['fifty_two_week_high'] = (float) $high;

        return $this;
    }

    public function getFiftyTwoWeekHigh(): float
    {
        return $this->items['fifty_two_week_high'] ?? 0.0;
    }

    public function setFiftyTwoWeekLow($low): self
    {
        $this->items['fifty_two_week_low'] = (float) $low;

        return $this;
    }

    public function getFiftyTwoWeekLow(): float
    {
        return $this->items['fifty_two_week_low'] ?? 0.0;
    }

    public function setForwardPE($pe): self
    {
        $this->items['forward_pe'] = (float) $pe;

        return $this;
    }

    public function getForwardPE(): float
    {
        return $this->items['forward_pe'] ?? 0.0;
    }

    public function setTrailingPE($pe): self
    {
        $this->items['trailing_pe'] = (float) $pe;

        return $this;
    }

    public function getTrailingPE(): float
    {
        return $this->items['trailing_pe'] ?? 0.0;
    }

    public function setMarketCap($cap): self
    {
        $this->items['market_cap'] = (int) $cap;

        return $this;
    }

    public function getMarketCap(): int
    {
        return $this->items['market_cap'] ?? 0;
    }

    public function setBookValue($value): self
    {
        $this->items['book_value'] = (float) $value;

        return $this;
    }

    public function getBookValue(): float
    {
        return $this->items['book_value'] ?? 0.0;
    }

    public function setLastDividendDate(mixed $date): self
    {
        $this->items['last_dividend_date'] = is_null($date) ? null : Carbon::parse($date)->format('Y-m-d H:i:s');

        return $this;
    }

    public function getLastDividendDate(): ?DateTime
    {
        return $this->items['last_dividend_date'] ?? null;
    }

    public function setDividendYield($yield): self
    {
        $this->items['dividend_yield'] = (float) $yield;

        return $this;
    }

    public function getDividendYield(): float
    {
        return $this->items['dividend_yield'] ?? 0.0;
    }
}
