<?php

namespace App\Interfaces\MarketData\Types;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class MarketDataType extends Collection
{
    public function __construct($items = [])
    {

        foreach ($this->getArrayableItems($items) as $key => $value) {

            $this->{$key} = $value;
        }
    }

    public function toArray()
    {
        return $this->items;
    }

    public function __set($key, $value)
    {
        $this->{'set'.Str::studly($key)}($value);
    }

    public function __get($key)
    {
        return $this->items[$key] ?? null;
    }
}
