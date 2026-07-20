<?php

declare(strict_types=1);

namespace App\Modules\Documents\Data;

final readonly class ReceiptExtractionContext
{
    public function __construct(
        public string $documentId,
        public bool $externalAiConsentGranted,
    ) {}
}
