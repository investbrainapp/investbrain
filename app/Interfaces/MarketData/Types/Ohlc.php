<?php

declare(strict_types=1);

namespace App\Interfaces\MarketData\Types;

use DateTime;
use Illuminate\Support\Carbon;

class Ohlc extends MarketDataType
{
    public function getRequiredItems(): array
    {
        return ['symbol', 'date', 'open', 'high', 'low', 'close'];
    }

    public function setSymbol(string $symbol): self
    {
        $this->items['symbol'] = $symbol;

        return $this;
    }

    public function getSymbol(): string
    {
        return $this->items['symbol'] ?? '';
    }

    public function setOpen($open): self
    {
        $this->items['open'] = (float) $open;

        return $this;
    }

    public function getOpen(): float
    {
        return $this->items['open'] ?? 0.0;
    }

    public function setHigh($high): self
    {
        $this->items['high'] = (float) $high;

        return $this;
    }

    public function getHigh(): float
    {
        return $this->items['high'] ?? 0.0;
    }

    public function setLow($low): self
    {
        $this->items['low'] = (float) $low;

        return $this;
    }

    public function getLow(): float
    {
        return $this->items['low'] ?? 0.0;
    }

    public function setClose($close): self
    {
        $this->items['close'] = (float) $close;

        return $this;
    }

    public function getClose(): float
    {
        return $this->items['close'] ?? 0.0;
    }

    public function setDate(string|DateTime $date): self
    {
        $this->items['date'] = Carbon::parse($date)->format('Y-m-d H:i:s');

        return $this;
    }

    public function getDate(): ?DateTime
    {
        return $this->items['date'] ?? null;
    }
}
