<?php

declare(strict_types=1);

namespace App\Modules\Documents\Data;

final readonly class ExtractedDocumentText
{
    public function __construct(
        public string $text,
        public string $provider,
    ) {}
}
