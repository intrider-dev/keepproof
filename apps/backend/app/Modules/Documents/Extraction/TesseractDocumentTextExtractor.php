<?php

declare(strict_types=1);

namespace App\Modules\Documents\Extraction;

use App\Modules\Documents\Contracts\DocumentTextExtractor;
use App\Modules\Documents\Data\ExtractedDocumentText;
use App\Modules\Documents\Exceptions\TransientDocumentFailure;
use Illuminate\Support\Facades\Process;

final class TesseractDocumentTextExtractor implements DocumentTextExtractor
{
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

                $pages[] = trim($result->output());
            }

            return new ExtractedDocumentText(trim(implode("\n\n", $pages)), 'tesseract-5.5');
        } finally {
            @unlink($source);
            foreach ($generated as $file) {
                @unlink($file);
            }
        }
    }
}
