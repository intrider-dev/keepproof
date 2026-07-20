<?php

declare(strict_types=1);

namespace KeepProof\DeadlineEngine\Tests\Unit;

use DateTimeImmutable;
use KeepProof\DeadlineEngine\DeadlineCalculator;
use KeepProof\DeadlineEngine\Exception\DateOutOfRange;
use KeepProof\DeadlineEngine\Exception\InvalidDeadlineRequest;
use KeepProof\DeadlineEngine\Exception\InvalidLocalDateTime;
use KeepProof\DeadlineEngine\Exception\InvalidManualOverride;
use KeepProof\DeadlineEngine\Exception\InvalidPeriod;
use KeepProof\DeadlineEngine\Exception\InvalidTimezone;
use KeepProof\DeadlineEngine\Value\DeadlineTerms;
use KeepProof\DeadlineEngine\Value\LocalDateTime;
use KeepProof\DeadlineEngine\Value\ManualOverride;
use KeepProof\DeadlineEngine\Value\Period;
use KeepProof\DeadlineEngine\Value\PurchaseDeadlineRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ValidationTest extends TestCase
{
    #[DataProvider('invalidLocalValues')]
    public function test_invalid_local_values_are_rejected(string $value, string $timezone): void
    {
        $this->expectException(InvalidLocalDateTime::class);

        LocalDateTime::fromLocal($value, $timezone);
    }

    /** @return iterable<string, array{string, string}> */
    public static function invalidLocalValues(): iterable
    {
        yield 'invalid calendar date' => ['2024-02-30 12:00:00', 'UTC'];
        yield 'year zero' => ['0000-01-01 12:00:00', 'UTC'];
        yield 'missing seconds' => ['2024-02-20 12:00', 'UTC'];
        yield 'nonexistent DST time' => ['2024-03-10 02:30:00', 'America/New_York'];
        yield 'ambiguous DST time' => ['2024-11-03 01:30:00', 'America/New_York'];
    }

    public function test_fixed_offset_timezone_is_rejected(): void
    {
        $this->expectException(InvalidTimezone::class);

        LocalDateTime::fromDateTime(new DateTimeImmutable('2025-01-01T12:00:00+03:00'));
    }

    #[DataProvider('invalidPeriods')]
    public function test_invalid_periods_are_rejected(callable $factory): void
    {
        $this->expectException(InvalidPeriod::class);

        $factory();
    }

    /** @return iterable<string, array{callable(): Period}> */
    public static function invalidPeriods(): iterable
    {
        yield 'zero' => [static fn (): Period => Period::days(0)];
        yield 'negative' => [static fn (): Period => Period::months(-1)];
        yield 'unbounded years' => [static fn (): Period => Period::years(1_001)];
    }

    public function test_empty_request_is_rejected(): void
    {
        $this->expectException(InvalidDeadlineRequest::class);

        new PurchaseDeadlineRequest(LocalDateTime::fromLocal('2025-01-01 12:00:00', 'UTC'), null, null);
    }

    #[DataProvider('invalidReasons')]
    public function test_invalid_override_reasons_are_rejected(string $reason): void
    {
        $this->expectException(InvalidManualOverride::class);

        new ManualOverride(LocalDateTime::fromLocal('2025-02-01 12:00:00', 'UTC'), $reason);
    }

    /** @return iterable<string, array{string}> */
    public static function invalidReasons(): iterable
    {
        yield 'empty' => ['   '];
        yield 'too long' => [str_repeat('a', 501)];
    }

    public function test_override_before_purchase_is_rejected(): void
    {
        $this->expectException(InvalidManualOverride::class);

        (new DeadlineCalculator())->calculate(new PurchaseDeadlineRequest(
            LocalDateTime::fromLocal('2025-02-01 12:00:00', 'UTC'),
            new DeadlineTerms(
                Period::days(14),
                manualOverride: new ManualOverride(
                    LocalDateTime::fromLocal('2025-01-31 12:00:00', 'UTC'),
                    'Invalid earlier date.',
                ),
            ),
            null,
        ));
    }

    public function test_calculation_outside_supported_date_range_is_rejected(): void
    {
        $this->expectException(DateOutOfRange::class);

        (new DeadlineCalculator())->calculate(new PurchaseDeadlineRequest(
            LocalDateTime::fromLocal('9999-01-01 12:00:00', 'UTC'),
            new DeadlineTerms(Period::years(1)),
            null,
        ));
    }
}
