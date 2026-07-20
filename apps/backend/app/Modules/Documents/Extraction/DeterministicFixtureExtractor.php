<?php

declare(strict_types=1);

namespace App\Modules\Documents\Extraction;

use App\Modules\Documents\Contracts\DocumentTextExtractor;
use App\Modules\Documents\Data\ExtractedDocumentText;

final class DeterministicFixtureExtractor implements DocumentTextExtractor
{
    private const FIXTURE_TEXT = <<<'TEXT'
MERCHANT: Northstar Home
PURCHASE DATE: 2026-07-08
RECEIPT: NS-1042
CURRENCY: USD
TOTAL: 139.50
ITEM: Linen floor lamp
RETURN DAYS: 30
WARRANTY MONTHS: 12
TEXT;

    public function extract(string $binary, string $mimeType): ExtractedDocumentText
    {
        return new ExtractedDocumentText(self::FIXTURE_TEXT, 'deterministic-fixture-v1');
    }
}
