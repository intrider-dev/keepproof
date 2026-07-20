<?php

declare(strict_types=1);

namespace App\Modules\Documents\Contracts;

use App\Modules\Documents\Data\ExtractedDocumentText;

interface DocumentTextExtractor
{
    public function extract(string $binary, string $mimeType): ExtractedDocumentText;
}
