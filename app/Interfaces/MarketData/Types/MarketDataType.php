<?php

declare(strict_types=1);

namespace App\Interfaces\MarketData\Types;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class MarketDataType extends Collection
{
    public function __construct($items = [])
    {

        $items = $this->getArrayableItems($items);

        foreach ($items as $key => $value) {

            $this->{$key} = $value;
        }
    }

    public function __set($key, $value)
    {
        $this->validateRequiredTypes($key, $value);

        $this->{$this->getSetMethodName($key)}($value);
    }

    public function __get($key)
    {
        return $this->items[$key] ?? null;
    }

    protected function getSetMethodName($key): string
    {
        return 'set'.Str::studly($key);
    }

    protected function validateRequiredTypes($key, $value, $type = null): void
    {
        $method = new \ReflectionMethod($this, $this->getSetMethodName($key));
        $params = $method->getParameters();

        // no required type
        if (is_null($type) && is_null($type = $params[0]->getType())) {
            return;
        }

        // can`t validate a mixed type
        if ($type == 'mixed') {
            return;
        }

        // has a union type, let's iterate
        if ($type instanceof \ReflectionUnionType) {

            foreach ($type->getTypes() as $subType) {
                $expected[] = $subType;

                try {
                    $this->validateRequiredTypes($key, $value, $subType);

                    return;
                } catch (\InvalidArgumentException) {
                }
            }
        }

        // check type
        if ($type instanceof \ReflectionNamedType) {
            $expected = $type->getName();

            if (get_debug_type($value) == $expected || ($type->allowsNull() && $value === null)) {

                return;
            }

            if (class_exists($expected) && is_subclass_of(get_debug_type($value), $expected)) {

                return;
            }
        }

        throw new \InvalidArgumentException("Invalid type for {$key}. Expected ".implode(', ', array_map(fn ($t) => $t, Arr::wrap($expected))).' but got '.get_debug_type($value));
    }
}
