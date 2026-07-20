<?php

declare(strict_types=1);

namespace App\Modules\Documents\Exceptions;

use RuntimeException;

final class PermanentDocumentFailure extends RuntimeException
{
    public function __construct(
        public readonly string $failureCode,
        public readonly string $userMessage,
    ) {
        parent::__construct($failureCode);
    }
}
