<?php

declare(strict_types=1);

namespace Tests\Unit\Shared;

use App\Modules\Shared\Logging\RedactSensitiveLogContext;
use DateTimeImmutable;
use Monolog\Formatter\JsonFormatter;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;

final class StructuredLoggingTest extends TestCase
{
    public function test_sensitive_context_is_redacted_before_json_formatting(): void
    {
        $record = new LogRecord(
            datetime: new DateTimeImmutable('2026-07-20T12:00:00Z'),
            channel: 'keepproof',
            level: Level::Warning,
            message: 'receipt.processing_retry',
            context: [
                'correlation_id' => 'f47ac10b-58cc-4372-a567-0e02b2c3d479',
                'document_id' => '01K00000000000000000000001',
                'storage_key' => 'households/private/receipt.png',
                'nested' => [
                    'email' => 'person@example.test',
                    'note' => 'Contact person@example.test using card 4242 4242 4242 4242.',
                ],
            ],
        );

        $processed = (new RedactSensitiveLogContext)($record);
        $formatted = (new JsonFormatter)->format($processed);
        $decoded = json_decode($formatted, true, flags: JSON_THROW_ON_ERROR);

        self::assertIsArray($decoded);
        $context = $decoded['context'] ?? null;
        self::assertIsArray($context);
        $nested = $context['nested'] ?? null;
        self::assertIsArray($nested);
        self::assertSame('receipt.processing_retry', $decoded['message'] ?? null);
        self::assertSame('01K00000000000000000000001', $context['document_id'] ?? null);
        self::assertSame('[REDACTED]', $context['storage_key'] ?? null);
        self::assertSame('[REDACTED]', $nested['email'] ?? null);
        self::assertStringNotContainsString('person@example.test', $formatted);
        self::assertStringNotContainsString('4242 4242 4242 4242', $formatted);
        self::assertStringEndsWith("\n", $formatted);
    }
}
