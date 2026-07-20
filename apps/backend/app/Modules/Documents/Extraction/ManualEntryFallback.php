<?php

declare(strict_types=1);

namespace App\Modules\Documents\Extraction;

use App\Modules\Documents\Contracts\ReceiptDataExtractor;
use App\Modules\Documents\Data\ExtractedDocumentText;
use App\Modules\Documents\Data\ReceiptExtraction;

final class ManualEntryFallback implements ReceiptDataExtractor
{
    public function extract(ExtractedDocumentText $document): ReceiptExtraction
    {
        return new ReceiptExtraction([], 'manual-entry', 'manual-entry-v1');
    }
}
