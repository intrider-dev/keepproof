<?php

declare(strict_types=1);

namespace App\Modules\Documents\Extraction;

use App\Modules\Documents\Contracts\ReceiptTextRedactor;

final class ConservativeReceiptTextRedactor implements ReceiptTextRedactor
{
    public function redact(string $text): string
    {
        $redacted = preg_replace(
            '/^(?<label>\s*(?:customer|member|email|e-mail|phone|telephone|address|card(?:\s+number)?|account)\s*[:#-]).*$/imu',
            '${label} [REDACTED]',
            $text,
        );
        $redacted = preg_replace(
            '/\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,63}\b/iu',
            '[REDACTED_EMAIL]',
            $redacted ?? $text,
        );
        $redacted = preg_replace(
            '/(?<!\d)(?:\d[ -]?){12,18}\d(?!\d)/u',
            '[REDACTED_NUMBER]',
            $redacted ?? $text,
        );

        return $redacted ?? $text;
    }
}
