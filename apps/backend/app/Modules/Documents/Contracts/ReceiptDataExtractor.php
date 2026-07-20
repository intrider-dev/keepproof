<?php

declare(strict_types=1);

namespace App\Modules\Documents\Contracts;

use App\Modules\Documents\Data\ExtractedDocumentText;
use App\Modules\Documents\Data\ReceiptExtraction;

interface ReceiptDataExtractor
{
    public function extract(ExtractedDocumentText $document): ReceiptExtraction;
}
