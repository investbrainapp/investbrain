<?php

namespace App\Interfaces\MarketData\Types;

use Illuminate\Support\Str;
use Illuminate\Support\Collection;

class MarketDataType extends Collection
{
    /**
     * 
     */
    public function __construct($items = [])
    {

        foreach($this->getArrayableItems($items) as $key => $value) {

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