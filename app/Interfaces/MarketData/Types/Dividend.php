<?php

namespace App\Interfaces\MarketData\Types;

use DateTime;
use Illuminate\Support\Carbon;
use App\Interfaces\MarketData\Types\MarketDataType;

class Dividend extends MarketDataType
{
    public function setSymbol(string $symbol): self
    {
        $this->items['symbol'] = $symbol;
        return $this;
    }

    public function getSymbol(): string
    {
        return $this->items['symbol'] ?? '';
    }

    public function setDividendAmount($dividendAmount): self
    {
        $this->items['dividend_amount'] = (float) $dividendAmount;
        return $this;
    }

    public function getDividendAmount(): float
    {
        return $this->items['dividend_amount'] ?? 0.0;
    }

    public function setDate(String|DateTime $date): self
    {
        $this->items['date'] = Carbon::parse($date)->format('Y-m-d H:i:s');
        return $this;
    }

    public function getDate(): ?DateTime
    {
        return $this->items['date'] ?? null;
    }
}