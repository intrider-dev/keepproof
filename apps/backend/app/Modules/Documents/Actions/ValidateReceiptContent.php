<?php

declare(strict_types=1);

namespace App\Modules\Documents\Actions;

use App\Modules\Documents\Exceptions\PermanentDocumentFailure;

final class ValidateReceiptContent
{
    public function execute(string $binary, string $mimeType, string $expectedSha256): void
    {
        if (! hash_equals($expectedSha256, hash('sha256', $binary))) {
            throw new PermanentDocumentFailure('checksum_mismatch', 'The stored receipt failed its integrity check.');
        }

        $validSignature = match ($mimeType) {
            'image/jpeg' => str_starts_with($binary, "\xFF\xD8\xFF"),
            'image/png' => str_starts_with($binary, "\x89PNG\r\n\x1A\n"),
            'application/pdf' => str_starts_with($binary, '%PDF-'),
            default => false,
        };
        if (! $validSignature) {
            throw new PermanentDocumentFailure('invalid_signature', 'The stored receipt type could not be verified.');
        }

        if ($mimeType === 'application/pdf'
            && preg_match('/\/(?:JavaScript|JS|Launch|EmbeddedFile|OpenAction)\b/i', $binary) === 1) {
            throw new PermanentDocumentFailure(
                'active_pdf_content',
                'The PDF contains active or embedded content and was quarantined.',
            );
        }
    }
}
