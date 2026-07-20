<?php

declare(strict_types=1);

namespace App\Modules\Shared\Logging;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

final class RedactSensitiveLogContext implements ProcessorInterface
{
    private const string REDACTED = '[REDACTED]';

    public function __invoke(LogRecord $record): LogRecord
    {
        return $record->with(
            context: $this->sanitize($record->context),
            extra: $this->sanitize($record->extra),
        );
    }

    /** @param array<mixed> $values
     * @return array<mixed>
     */
    private function sanitize(array $values): array
    {
        $sanitized = [];
        foreach ($values as $key => $value) {
            if (is_string($key) && $this->isSensitiveKey($key)) {
                $sanitized[$key] = self::REDACTED;

                continue;
            }

            $sanitized[$key] = is_array($value)
                ? $this->sanitize($value)
                : $this->redactIdentifiers($value);
        }

        return $sanitized;
    }

    private function isSensitiveKey(string $key): bool
    {
        return preg_match(
            '/(?:password|passphrase|token|secret|authorization|cookie|api[_-]?key|storage[_-]?key|signed[_-]?url|raw[_-]?ocr|receipt[_-]?text|document[_-]?content|email|url)/i',
            $key,
        ) === 1;
    }

    private function redactIdentifiers(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $value = preg_replace(
            '/\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,63}\b/iu',
            self::REDACTED,
            $value,
        ) ?? self::REDACTED;

        return preg_replace(
            '/(?<!\d)(?:\d[ -]?){12,18}\d(?!\d)/u',
            self::REDACTED,
            $value,
        ) ?? self::REDACTED;
    }
}
