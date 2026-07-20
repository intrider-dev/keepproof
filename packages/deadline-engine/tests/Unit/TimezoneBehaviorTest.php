<?php

declare(strict_types=1);

namespace KeepProof\DeadlineEngine\Tests\Unit;

use DateTimeImmutable;
use DateTimeZone;
use KeepProof\DeadlineEngine\DeadlineCalculator;
use KeepProof\DeadlineEngine\RuleCode;
use KeepProof\DeadlineEngine\Value\DeadlineTerms;
use KeepProof\DeadlineEngine\Value\LocalDateTime;
use KeepProof\DeadlineEngine\Value\Period;
use KeepProof\DeadlineEngine\Value\PurchaseDeadlineRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TimezoneBehaviorTest extends TestCase
{
    #[DataProvider('dstCases')]
    public function test_calendar_days_preserve_wall_time_across_dst(
        string $purchase,
        string $expected,
        int $expectedElapsedHours,
    ): void {
        $purchasedAt = LocalDateTime::fromLocal($purchase, 'America/New_York');
        $result = (new DeadlineCalculator())->calculate(new PurchaseDeadlineRequest(
            $purchasedAt,
            new DeadlineTerms(Period::days(1)),
            null,
        ));
        $deadline = $result->returnDeadline;

        self::assertNotNull($deadline);
        self::assertSame($expected, $deadline->effectiveDeadline->dateTime()->format('Y-m-d H:i:s P'));
        self::assertSame(
            $expectedElapsedHours * 3600,
            $deadline->effectiveDeadline->dateTime()->getTimestamp() - $purchasedAt->dateTime()->getTimestamp(),
        );
        self::assertContains(
            RuleCode::TimezoneOffsetChanged,
            array_map(static fn ($step) => $step->code, $deadline->trace),
        );
    }

    /** @return iterable<string, array{string, string, int}> */
    public static function dstCases(): iterable
    {
        yield 'spring forward is a 23-hour calendar day' => [
            '2024-03-09 12:00:00',
            '2024-03-10 12:00:00 -04:00',
            23,
        ];
        yield 'fall back is a 25-hour calendar day' => [
            '2024-11-02 12:00:00',
            '2024-11-03 12:00:00 -05:00',
            25,
        ];
    }

    public function test_a_calculated_nonexistent_wall_time_moves_forward(): void
    {
        $result = (new DeadlineCalculator())->calculate(new PurchaseDeadlineRequest(
            LocalDateTime::fromLocal('2024-01-31 02:30:00', 'Europe/Berlin'),
            new DeadlineTerms(Period::months(2)),
            null,
        ));
        $deadline = $result->returnDeadline;

        self::assertNotNull($deadline);
        self::assertSame(
            '2024-03-31 03:30:00 +02:00',
            $deadline->effectiveDeadline->dateTime()->format('Y-m-d H:i:s P'),
        );
        self::assertContains(
            RuleCode::DstGapResolved,
            array_map(static fn ($step) => $step->code, $deadline->trace),
        );
    }

    public function test_an_offset_qualified_ambiguous_instant_can_be_resolved_explicitly(): void
    {
        $instant = (new DateTimeImmutable('2024-11-03T01:30:00-04:00'))
            ->setTimezone(new DateTimeZone('America/New_York'));
        $value = LocalDateTime::fromDateTime($instant);

        self::assertSame('2024-11-03T01:30:00.000000-04:00', $value->toRfc3339());
        self::assertSame('America/New_York', $value->timezone());
    }
}
