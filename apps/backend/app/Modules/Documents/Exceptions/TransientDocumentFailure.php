<?php

declare(strict_types=1);

namespace App\Modules\Documents\Exceptions;

use RuntimeException;

final class TransientDocumentFailure extends RuntimeException
{
    public function __construct(public readonly string $failureCode)
    {
        parent::__construct($failureCode);
    }
}
