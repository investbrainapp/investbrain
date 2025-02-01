<?php

declare(strict_types=1);

namespace App\Interfaces\MarketData\Types;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class MarketDataType extends Collection
{
    public function __construct($items = [])
    {

        $items = $this->getArrayableItems($items);

        // check for required items
        if (! empty($missing = array_diff($this->getRequiredItems(), array_keys($items)))) {
            throw new \Exception('Missing required properties ('.implode(', ', $missing).')');
        }

        foreach ($items as $key => $value) {

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

    public function getRequiredItems(): array
    {
        return [];
    }
}
