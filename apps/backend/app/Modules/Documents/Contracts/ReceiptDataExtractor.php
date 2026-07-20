<?php

declare(strict_types=1);

namespace App\Modules\Documents\Contracts;

use App\Modules\Documents\Data\ExtractedDocumentText;
use App\Modules\Documents\Data\ReceiptExtraction;
use App\Modules\Documents\Data\ReceiptExtractionContext;

interface ReceiptDataExtractor
{
    public function extract(
        ExtractedDocumentText $document,
        ReceiptExtractionContext $context,
    ): ReceiptExtraction;
}
