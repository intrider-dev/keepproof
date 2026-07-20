<?php

declare(strict_types=1);

namespace Tests\Feature\Documents;

use App\Modules\Documents\Data\ReceiptExtractionContext;
use App\Modules\Documents\Extraction\ReceiptPatternExtractor;
use App\Modules\Documents\Extraction\TesseractDocumentTextExtractor;
use Illuminate\Support\Facades\Process;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('ocr')]
final class TesseractReceiptContractTest extends TestCase
{
    #[DataProvider('fixtureProvider')]
    public function test_local_ocr_extracts_structured_fields_from_a_synthetic_receipt(
        string $fixture,
        string $mimeType,
        bool $requiresPdfTools,
    ): void {
        $this->requireBinary('tesseract');
        if ($requiresPdfTools) {
            $this->requireBinary('pdftoppm');
        }

        $path = base_path('../../tests/fixtures/receipts/generated/'.$fixture);
        $binary = file_get_contents($path);
        self::assertIsString($binary);

        $text = (new TesseractDocumentTextExtractor)->extract($binary, $mimeType);
        self::assertStringContainsStringIgnoringCase('NORTHSTAR', $text->text);
        self::assertStringContainsString('2026-06-12', $text->text);

        $extraction = (new ReceiptPatternExtractor)->extract(
            $text,
            new ReceiptExtractionContext('ocr-contract-fixture', false),
        );
        $fields = [];
        foreach ($extraction->fields as $field) {
            $fields[$field->key] = $field->normalizedValue;
        }

        self::assertSame('2026-06-12', $fields['purchase_date'] ?? null);
        self::assertSame('KP-2026-0612', $fields['receipt_number'] ?? null);
        self::assertSame('USD', $fields['currency'] ?? null);
        self::assertSame('12999', $fields['total_minor'] ?? null);
        self::assertSame('30', $fields['return_period_quantity'] ?? null);
        self::assertSame('24', $fields['warranty_period_quantity'] ?? null);
    }

    /** @return iterable<string, array{string, string, bool}> */
    public static function fixtureProvider(): iterable
    {
        yield 'PNG input' => ['northstar-receipt.png', 'image/png', false];
        yield 'PDF input' => ['northstar-receipt.pdf', 'application/pdf', true];
    }

    private function requireBinary(string $binary): void
    {
        try {
            $versionFlag = $binary === 'pdftoppm' ? '-v' : '--version';
            $probe = Process::timeout(5)->run([$binary, $versionFlag]);
        } catch (\Throwable) {
            self::markTestSkipped($binary.' is available in the backend container, not on this host.');
        }

        if ($probe->failed()) {
            self::markTestSkipped($binary.' is available in the backend container, not on this host.');
        }
    }
}
