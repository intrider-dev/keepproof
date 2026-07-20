<?php

declare(strict_types=1);

namespace Tests\Unit\Documents;

use App\Modules\Documents\Data\ExtractedDocumentText;
use App\Modules\Documents\Data\ReceiptExtractionContext;
use App\Modules\Documents\Extraction\ReceiptPatternExtractor;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ReceiptPatternExtractorTest extends TestCase
{
    /** @param array<string, string> $expected */
    #[DataProvider('receiptTextProvider')]
    public function test_extracts_supported_local_receipt_patterns(string $text, array $expected): void
    {
        $extraction = (new ReceiptPatternExtractor)->extract(
            new ExtractedDocumentText($text, 'test-ocr'),
            new ReceiptExtractionContext('test-document', false),
        );
        $actual = [];
        foreach ($extraction->fields as $field) {
            $actual[$field->key] = $field->normalizedValue;
        }

        self::assertSame($expected, $actual);
        self::assertSame('deterministic-patterns', $extraction->provider);
    }

    /** @return iterable<string, array{string, array<string, string>}> */
    public static function receiptTextProvider(): iterable
    {
        yield 'labeled fixture output' => [
            <<<'TEXT'
MERCHANT: Northstar Home
PURCHASE DATE: 2026-07-08
RECEIPT: NS-1042
CURRENCY: USD
TOTAL: 139.50
ITEM: Linen floor lamp
RETURN DAYS: 30
WARRANTY MONTHS: 12
TEXT,
            [
                'merchant' => 'Northstar Home',
                'purchase_date' => '2026-07-08',
                'receipt_number' => 'NS-1042',
                'currency' => 'USD',
                'total_minor' => '13950',
                'item_name' => 'Linen floor lamp',
                'return_period_quantity' => '30',
                'return_period_unit' => 'days',
                'warranty_period_quantity' => '12',
                'warranty_period_unit' => 'months',
            ],
        ];

        yield 'tesseract-like receipt output' => [
            <<<'TEXT'
NORTHSTAR ELECTRONICS
100 DEMO AVENUE
EXAMPLE CITY
RECEIPT       kP-2026-0612
PURCHASE DATE 2026-06-12
TRAILVIEW CAMERA BACKPACK
QTY 1              USD 129.99
TOTAL,             USD 129.99
RETURN WINDOW: 30 DAYS
LIMITED WARRANTY: 24 MONTHS
TEXT,
            [
                'merchant' => 'NORTHSTAR ELECTRONICS',
                'purchase_date' => '2026-06-12',
                'receipt_number' => 'KP-2026-0612',
                'currency' => 'USD',
                'total_minor' => '12999',
                'item_name' => 'TRAILVIEW CAMERA BACKPACK',
                'return_period_quantity' => '30',
                'return_period_unit' => 'days',
                'warranty_period_quantity' => '24',
                'warranty_period_unit' => 'months',
            ],
        ];
    }
}
