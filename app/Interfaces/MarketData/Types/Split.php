<?php

namespace App\Interfaces\MarketData\Types;

use DateTime;
use Illuminate\Support\Carbon;

class Split extends MarketDataType
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

    public function setSplitAmount($splitAmount): self
    {
        $this->items['split_amount'] = (float) $splitAmount;

        return $this;
    }

    public function getSplitAmount(): float
    {
        return $this->items['split_amount'] ?? 0.0;
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
