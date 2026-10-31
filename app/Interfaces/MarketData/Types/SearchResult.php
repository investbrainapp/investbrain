<?php

declare(strict_types=1);

namespace App\Interfaces\MarketData\Types;

class SearchResult extends MarketDataType
{
    public function setSymbol(string $symbol): self
    {
        $this->items['symbol'] = (string) $symbol;

        return $this;
    }

    public function getSymbol(): string
    {
        return $this->items['symbol'] ?? '';
    }

    public function setName($name): self
    {
        if (! empty($name)) {
            $this->items['name'] = (string) $name;
        }

        return $this;
    }

    public function getName(): string
    {
        return $this->items['name'] ?? '';
    }

    public function setType($type): self
    {
        if (! empty($type)) {
            $this->items['type'] = (string) $type;
        }

        return $this;
    }

    public function getType(): string
    {
        return $this->items['type'] ?? '';
    }

    public function setExchange($exchange): self
    {
        if (! empty($exchange)) {
            $this->items['exchange'] = (string) $exchange;
        }

        return $this;
    }

    public function getExchange(): string
    {
        return $this->items['exchange'] ?? '';
    }
}
