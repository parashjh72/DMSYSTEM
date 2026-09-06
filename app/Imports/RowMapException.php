<?php

namespace App\Imports;

use RuntimeException;

class RowMapException extends RuntimeException
{
    public function __construct(
        public readonly string $errorType,
        string $message,
    ) {
        parent::__construct($message);
    }
}
