<?php

namespace App\Services\Import;

use App\Enums\ImportError;
use RuntimeException;

/**
 * Raised when an import cannot produce a recipe for a known reason.
 */
class ImportFailedException extends RuntimeException
{
    public function __construct(public readonly ImportError $error, string $detail = '')
    {
        parent::__construct(trim("{$error->value} {$detail}"));
    }
}
