<?php

declare(strict_types=1);

namespace Tests\Unit\Documents;

use App\Modules\Documents\Exceptions\PermanentDocumentFailure;
use App\Modules\Documents\Extraction\TesseractDocumentTextExtractor;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

final class TesseractDocumentTextExtractorTest extends TestCase
{
    public function test_document_content_is_never_interpreted_as_a_shell_command(): void
    {
        $hostileContent = 'synthetic-image; touch /tmp/receipt-owned && calc.exe';
        Process::fake(static fn (): string => "MERCHANT: Safe Store\nTOTAL: 10.00")
            ->preventStrayProcesses();

        $result = (new TesseractDocumentTextExtractor)->extract($hostileContent, 'image/png');

        self::assertSame("MERCHANT: Safe Store\nTOTAL: 10.00", $result->text);
        Process::assertRan(static function (PendingProcess $process) use ($hostileContent): bool {
            self::assertIsArray($process->command);
            self::assertSame('tesseract', $process->command[0] ?? null);
            self::assertSame('stdout', $process->command[2] ?? null);
            self::assertSame(['-l', 'eng', '--psm', '6'], array_slice($process->command, 3));
            self::assertNotContains($hostileContent, $process->command);

            return true;
        });
    }

    public function test_excessive_ocr_output_is_rejected_without_retrying_forever(): void
    {
        Process::fake(static fn (): string => str_repeat('A', 65_537))->preventStrayProcesses();

        try {
            (new TesseractDocumentTextExtractor)->extract('synthetic-image', 'image/png');
            self::fail('Excessive OCR output must be rejected.');
        } catch (PermanentDocumentFailure $exception) {
            self::assertSame('ocr_output_too_large', $exception->failureCode);
        }
    }
}
