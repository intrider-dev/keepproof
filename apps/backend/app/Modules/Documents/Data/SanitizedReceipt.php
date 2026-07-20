<?php

declare(strict_types=1);

namespace App\Modules\Documents\Data;

final readonly class SanitizedReceipt
{
    public function __construct(
        public string $binary,
        public string $mimeType,
        public string $extension,
        public string $displayFilename,
        public int $sizeBytes,
        public string $sha256,
    ) {}
}
