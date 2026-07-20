<?php

declare(strict_types=1);

namespace App\Modules\Documents\Extraction;

use App\Modules\Documents\Contracts\DocumentTextExtractor;
use App\Modules\Documents\Data\ExtractedDocumentText;
use App\Modules\Documents\Exceptions\PermanentDocumentFailure;
use App\Modules\Documents\Exceptions\TransientDocumentFailure;
use Illuminate\Support\Facades\Process;

final class TesseractDocumentTextExtractor implements DocumentTextExtractor
{
    private const MAX_PAGE_OUTPUT_BYTES = 65_536;

    private const MAX_DOCUMENT_OUTPUT_BYTES = 131_072;

    public function extract(string $binary, string $mimeType): ExtractedDocumentText
    {
        $source = tempnam(sys_get_temp_dir(), 'kp_receipt_');
        if ($source === false || file_put_contents($source, $binary, LOCK_EX) === false) {
            throw new TransientDocumentFailure('temporary_file_unavailable');
        }

        $generated = [];
        try {
            $images = [$source];
            if ($mimeType === 'application/pdf') {
                $prefix = sys_get_temp_dir().DIRECTORY_SEPARATOR.'kp_pdf_'.bin2hex(random_bytes(8));
                $conversion = Process::timeout(60)->run([
                    'pdftoppm', '-f', '1', '-l', '3', '-r', '200', '-png', $source, $prefix,
                ]);
                if ($conversion->failed()) {
                    throw new TransientDocumentFailure('pdf_render_failed');
                }

                $found = glob($prefix.'-*.png');
                if ($found === false || $found === []) {
                    throw new TransientDocumentFailure('pdf_render_empty');
                }

                $generated = $found;
                $images = $found;
            }

            $pages = [];
            foreach ($images as $image) {
                $result = Process::timeout(60)->run(['tesseract', $image, 'stdout', '-l', 'eng', '--psm', '6']);
                if ($result->failed()) {
                    throw new TransientDocumentFailure('tesseract_failed');
                }

                $page = trim($result->output());
                if (strlen($page) > self::MAX_PAGE_OUTPUT_BYTES) {
                    throw new PermanentDocumentFailure(
                        'ocr_output_too_large',
                        'The receipt produced too much OCR text. Enter the purchase details manually.',
                    );
                }
                $pages[] = $page;
            }

            $text = trim(implode("\n\n", $pages));
            if (strlen($text) > self::MAX_DOCUMENT_OUTPUT_BYTES) {
                throw new PermanentDocumentFailure(
                    'ocr_output_too_large',
                    'The receipt produced too much OCR text. Enter the purchase details manually.',
                );
            }

            return new ExtractedDocumentText($text, 'tesseract-5.5');
        } finally {
            @unlink($source);
            foreach ($generated as $file) {
                @unlink($file);
            }
        }
    }
}
