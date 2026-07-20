<?php

declare(strict_types=1);

namespace KeepProof\DeadlineEngine\Tests\Unit;

use KeepProof\DeadlineEngine\DeadlineCalculator;
use KeepProof\DeadlineEngine\EndOfMonthPolicy;
use KeepProof\DeadlineEngine\Value\DeadlineTerms;
use KeepProof\DeadlineEngine\Value\LocalDateTime;
use KeepProof\DeadlineEngine\Value\Period;
use KeepProof\DeadlineEngine\Value\PurchaseDeadlineRequest;
use PHPUnit\Framework\TestCase;

final class BoundaryPropertyTest extends TestCase
{
    public function test_month_arithmetic_never_overflows_into_the_month_after_the_target(): void
    {
        $calculator = new DeadlineCalculator();
        $assertions = 0;

        foreach (range(1996, 2032) as $year) {
            foreach (range(1, 12) as $month) {
                foreach ([28, 29, 30, 31] as $day) {
                    if (!checkdate($month, $day, $year)) {
                        continue;
                    }

                    foreach ([1, 2, 6, 12, 24] as $months) {
                        $purchase = LocalDateTime::fromLocal(
                            sprintf('%04d-%02d-%02d 12:34:56', $year, $month, $day),
                            'UTC',
                        );
                        $result = $calculator->calculate(new PurchaseDeadlineRequest(
                            $purchase,
                            new DeadlineTerms(Period::months($months), endOfMonthPolicy: EndOfMonthPolicy::Clamp),
                            null,
                        ));
                        $deadline = $result->returnDeadline?->effectiveDeadline->dateTime();

                        self::assertNotNull($deadline);
                        $expectedMonthIndex = ($year * 12) + ($month - 1) + $months;
                        self::assertSame(intdiv($expectedMonthIndex, 12), (int) $deadline->format('Y'));
                        self::assertSame(($expectedMonthIndex % 12) + 1, (int) $deadline->format('n'));
                        self::assertLessThanOrEqual($day, (int) $deadline->format('j'));
                        self::assertSame('12:34:56', $deadline->format('H:i:s'));
                        ++$assertions;
                    }
                }
            }
        }

        self::assertGreaterThan(5_000, $assertions);
    }

    public function test_clamped_anniversaries_are_valid_across_four_centuries(): void
    {
        $calculator = new DeadlineCalculator();

        foreach (range(1600, 2396, 4) as $leapYear) {
            if (!checkdate(2, 29, $leapYear)) {
                continue;
            }

            $result = $calculator->calculate(new PurchaseDeadlineRequest(
                LocalDateTime::fromLocal(sprintf('%04d-02-29 08:00:00', $leapYear), 'UTC'),
                null,
                new DeadlineTerms(Period::years(1)),
            ));
            $deadline = $result->warrantyDeadline?->effectiveDeadline->dateTime();

            self::assertNotNull($deadline);
            self::assertSame(sprintf('%04d-02-28', $leapYear + 1), $deadline->format('Y-m-d'));
        }
    }
}
