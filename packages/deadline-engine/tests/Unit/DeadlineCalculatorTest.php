<?php

declare(strict_types=1);

namespace KeepProof\DeadlineEngine\Tests\Unit;

use KeepProof\DeadlineEngine\CountingConvention;
use KeepProof\DeadlineEngine\DeadlineCalculator;
use KeepProof\DeadlineEngine\DeadlineSource;
use KeepProof\DeadlineEngine\EndOfMonthPolicy;
use KeepProof\DeadlineEngine\RuleCode;
use KeepProof\DeadlineEngine\Value\DeadlineTerms;
use KeepProof\DeadlineEngine\Value\LocalDateTime;
use KeepProof\DeadlineEngine\Value\ManualOverride;
use KeepProof\DeadlineEngine\Value\Period;
use KeepProof\DeadlineEngine\Value\PurchaseDeadlineRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DeadlineCalculatorTest extends TestCase
{
    #[DataProvider('deadlineCases')]
    public function test_it_calculates_calendar_deadlines(
        string $purchase,
        Period $period,
        CountingConvention $convention,
        EndOfMonthPolicy $monthEndPolicy,
        string $expected,
    ): void {
        $result = (new DeadlineCalculator())->calculate(new PurchaseDeadlineRequest(
            purchasedAt: LocalDateTime::fromLocal($purchase, 'Europe/London'),
            returnTerms: new DeadlineTerms($period, $convention, $monthEndPolicy),
            warrantyTerms: null,
        ));

        self::assertNotNull($result->returnDeadline);
        self::assertSame(
            $expected,
            $result->returnDeadline->effectiveDeadline->dateTime()->format('Y-m-d H:i:s P'),
        );
        self::assertSame(DeadlineSource::Calculated, $result->returnDeadline->source);
    }

    /**
     * @return iterable<string, array{string, Period, CountingConvention, EndOfMonthPolicy, string}>
     */
    public static function deadlineCases(): iterable
    {
        yield 'thirty days excluding purchase day' => [
            '2024-01-01 10:15:00',
            Period::days(30),
            CountingConvention::PurchaseDayExclusive,
            EndOfMonthPolicy::Clamp,
            '2024-01-31 10:15:00 +00:00',
        ];
        yield 'thirty days including purchase day' => [
            '2024-01-01 10:15:00',
            Period::days(30),
            CountingConvention::PurchaseDayInclusive,
            EndOfMonthPolicy::Clamp,
            '2024-01-30 10:15:00 +00:00',
        ];
        yield 'one inclusive day ends at purchase wall time' => [
            '2024-06-10 23:59:59',
            Period::days(1),
            CountingConvention::PurchaseDayInclusive,
            EndOfMonthPolicy::Clamp,
            '2024-06-10 23:59:59 +01:00',
        ];
        yield 'leap year month is clamped' => [
            '2024-01-31 09:00:00',
            Period::months(1),
            CountingConvention::PurchaseDayExclusive,
            EndOfMonthPolicy::Clamp,
            '2024-02-29 09:00:00 +00:00',
        ];
        yield 'non-leap year month is clamped' => [
            '2023-01-31 09:00:00',
            Period::months(1),
            CountingConvention::PurchaseDayExclusive,
            EndOfMonthPolicy::Clamp,
            '2023-02-28 09:00:00 +00:00',
        ];
        yield 'leap day plus one year is clamped' => [
            '2024-02-29 09:00:00',
            Period::years(1),
            CountingConvention::PurchaseDayExclusive,
            EndOfMonthPolicy::Clamp,
            '2025-02-28 09:00:00 +00:00',
        ];
        yield 'month-end is preserved when requested' => [
            '2024-02-29 09:00:00',
            Period::months(1),
            CountingConvention::PurchaseDayExclusive,
            EndOfMonthPolicy::PreserveEndOfMonth,
            '2024-03-31 09:00:00 +01:00',
        ];
        yield 'clamp does not preserve a valid ordinal day' => [
            '2024-02-29 09:00:00',
            Period::months(1),
            CountingConvention::PurchaseDayExclusive,
            EndOfMonthPolicy::Clamp,
            '2024-03-29 09:00:00 +00:00',
        ];
        yield 'inclusive calendar month ends one day before boundary' => [
            '2024-01-31 09:00:00',
            Period::months(1),
            CountingConvention::PurchaseDayInclusive,
            EndOfMonthPolicy::Clamp,
            '2024-02-28 09:00:00 +00:00',
        ];
        yield 'century not divisible by 400 is not leap' => [
            '2096-02-29 09:00:00',
            Period::years(4),
            CountingConvention::PurchaseDayExclusive,
            EndOfMonthPolicy::Clamp,
            '2100-02-28 09:00:00 +00:00',
        ];
        yield 'century divisible by 400 is leap' => [
            '2396-02-29 09:00:00',
            Period::years(4),
            CountingConvention::PurchaseDayExclusive,
            EndOfMonthPolicy::Clamp,
            '2400-02-29 09:00:00 +00:00',
        ];
    }

    public function test_it_calculates_return_and_warranty_independently(): void
    {
        $result = (new DeadlineCalculator())->calculate(new PurchaseDeadlineRequest(
            purchasedAt: LocalDateTime::fromLocal('2025-05-20 14:30:00', 'Asia/Tokyo'),
            returnTerms: new DeadlineTerms(Period::days(14)),
            warrantyTerms: new DeadlineTerms(Period::years(2)),
        ));

        self::assertSame(
            '2025-06-03',
            $result->returnDeadline?->effectiveDeadline->dateTime()->format('Y-m-d'),
        );
        self::assertSame(
            '2027-05-20',
            $result->warrantyDeadline?->effectiveDeadline->dateTime()->format('Y-m-d'),
        );
        self::assertNotNull($result->returnDeadline);
        self::assertNotNull($result->warrantyDeadline);
    }

    public function test_a_manual_override_retains_the_calculated_baseline_and_reason(): void
    {
        $terms = new DeadlineTerms(
            period: Period::days(30),
            manualOverride: new ManualOverride(
                LocalDateTime::fromLocal('2025-03-15 18:00:00', 'America/New_York'),
                'Merchant granted an extension.',
            ),
        );
        $result = (new DeadlineCalculator())->calculate(new PurchaseDeadlineRequest(
            LocalDateTime::fromLocal('2025-02-01 18:00:00', 'America/New_York'),
            $terms,
            null,
        ));
        $deadline = $result->returnDeadline;

        self::assertNotNull($deadline);
        self::assertSame(DeadlineSource::ManualOverride, $deadline->source);
        self::assertSame('2025-03-15', $deadline->effectiveDeadline->dateTime()->format('Y-m-d'));
        self::assertSame('2025-03-03', $deadline->calculatedDeadline->dateTime()->format('Y-m-d'));
        self::assertSame(RuleCode::ManualOverrideApplied, $deadline->trace[array_key_last($deadline->trace)]->code);
        self::assertStringContainsString('manual override applied', $deadline->ruleSummary);
    }

    public function test_result_is_explainable_and_json_serializable(): void
    {
        $result = (new DeadlineCalculator())->calculate(new PurchaseDeadlineRequest(
            LocalDateTime::fromLocal('2024-01-31 12:00:00', 'UTC'),
            new DeadlineTerms(Period::months(1)),
            null,
        ));
        $payload = $result->jsonSerialize();
        $returnDeadline = $result->returnDeadline;

        self::assertNotNull($returnDeadline);
        $returnPayload = $returnDeadline->jsonSerialize();
        self::assertSame('return', $returnPayload['kind']);
        self::assertSame('calculated', $returnPayload['source']);
        self::assertSame('UTC', $returnPayload['effective_deadline']['timezone']);
        self::assertNull($payload['warranty']);

        $codes = array_column($returnPayload['trace'], 'code');
        self::assertContains('period_applied', $codes);
        self::assertContains('end_of_month_resolved', $codes);
    }
}
