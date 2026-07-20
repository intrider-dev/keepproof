<?php

declare(strict_types=1);

namespace App\Modules\Documents\Data;

final readonly class ReceiptExtraction
{
    /** @param list<ExtractedField> $fields */
    public function __construct(
        public array $fields,
        public string $provider,
        public string $promptVersion,
    ) {}
}
