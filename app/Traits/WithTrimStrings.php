<?php

namespace App\Traits;

use Illuminate\Support\Str;

trait WithTrimStrings
{
    public function trimExceptions(): array
    {
        return [];
    }

    public function updatedWithTrimStrings(string $property, mixed $value): void
    {
        if (is_string($value) && !in_array($property, $this->trimExceptions())) {
            $this->fill([
                $property => Str::trim($value),
            ]);
        }
    }
}