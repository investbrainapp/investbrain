<?php

declare(strict_types=1);

namespace App\Imports;

use Exception;

class ImportValidationException extends Exception
{
    /**
     * @param  list<array{sheet: string, row: int|null, column: string|null, value: string|null, message: string}>  $errors
     */
    public function __construct(
        public readonly array $errors
    ) {
        parent::__construct(trans_choice(
            'We found a problem in your file. Nothing was imported.|We found problems in your file. Nothing was imported.',
            count($errors)
        ));
    }
}
