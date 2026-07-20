<?php

declare(strict_types=1);

namespace App\Modules\Documents\Contracts;

interface ReceiptTextRedactor
{
    public function redact(string $text): string;
}
