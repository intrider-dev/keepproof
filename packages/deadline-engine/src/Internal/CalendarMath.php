<?php

declare(strict_types=1);

namespace KeepProof\DeadlineEngine\Internal;

use DateTimeImmutable;
use KeepProof\DeadlineEngine\EndOfMonthPolicy;
use KeepProof\DeadlineEngine\Exception\DateOutOfRange;
use KeepProof\DeadlineEngine\PeriodUnit;
use KeepProof\DeadlineEngine\Value\Period;

/** @internal */
final class CalendarMath
{
    private function __construct()
    {
    }

    public static function add(
        DateTimeImmutable $source,
        Period $period,
        EndOfMonthPolicy $monthEndPolicy,
    ): CalendarAddition {
        return match ($period->unit) {
            PeriodUnit::Days => self::addDays($source, $period->quantity),
            PeriodUnit::Months => self::addMonths($source, $period->quantity, $monthEndPolicy),
            PeriodUnit::Years => self::addYears($source, $period->quantity, $monthEndPolicy),
        };
    }

    public static function addCalendarDays(DateTimeImmutable $source, int $days): DateTimeImmutable
    {
        if ($days === 0) {
            return $source;
        }

        $modifier = sprintf('%+d days', $days);
        $result = $source->modify($modifier);
        self::assertSupportedYear((int) $result->format('Y'));

        return $result;
    }

    public static function previousDayKeepingWallTime(
        DateTimeImmutable $boundary,
        DateTimeImmutable $wallTimeSource,
    ): DateTimeImmutable {
        $previousDate = $boundary->setTime(12, 0)->modify('-1 day');
        $result = self::atDateWithWallTime($previousDate, $wallTimeSource);
        self::assertSupportedYear((int) $result->format('Y'));

        return $result;
    }

    public static function addDays(DateTimeImmutable $source, int $days): CalendarAddition
    {
        if ($days < 0) {
            throw new \LogicException('Internal day additions cannot be negative.');
        }

        $result = self::addCalendarDays($source, $days);
        $day = (int) $source->format('j');

        return new CalendarAddition(
            dateTime: $result,
            monthEndRuleUsed: false,
            sourceWasMonthEnd: false,
            dayWasClamped: false,
            sourceDay: $day,
            targetDay: (int) $result->format('j'),
        );
    }

    private static function addMonths(
        DateTimeImmutable $source,
        int $months,
        EndOfMonthPolicy $policy,
    ): CalendarAddition {
        $sourceYear = (int) $source->format('Y');
        $sourceMonth = (int) $source->format('n');
        $sourceDay = (int) $source->format('j');
        $monthIndex = ($sourceYear * 12) + ($sourceMonth - 1) + $months;
        $targetYear = intdiv($monthIndex, 12);
        $targetMonth = ($monthIndex % 12) + 1;

        self::assertSupportedYear($targetYear);

        return self::resolveMonthDay($source, $targetYear, $targetMonth, $sourceDay, $policy);
    }

    private static function addYears(
        DateTimeImmutable $source,
        int $years,
        EndOfMonthPolicy $policy,
    ): CalendarAddition {
        $targetYear = (int) $source->format('Y') + $years;
        self::assertSupportedYear($targetYear);

        return self::resolveMonthDay(
            source: $source,
            targetYear: $targetYear,
            targetMonth: (int) $source->format('n'),
            sourceDay: (int) $source->format('j'),
            policy: $policy,
        );
    }

    private static function resolveMonthDay(
        DateTimeImmutable $source,
        int $targetYear,
        int $targetMonth,
        int $sourceDay,
        EndOfMonthPolicy $policy,
    ): CalendarAddition {
        $sourceMonthLength = self::daysInMonth(
            (int) $source->format('Y'),
            (int) $source->format('n'),
        );
        $targetMonthLength = self::daysInMonth($targetYear, $targetMonth);
        $sourceWasMonthEnd = $sourceDay === $sourceMonthLength;
        $preserveMonthEnd = $policy === EndOfMonthPolicy::PreserveEndOfMonth && $sourceWasMonthEnd;
        $targetDay = $preserveMonthEnd
            ? $targetMonthLength
            : min($sourceDay, $targetMonthLength);

        return new CalendarAddition(
            dateTime: self::atDateWithWallTime(
                DateTimeImmutable::createFromFormat(
                    '!Y-n-j H:i:s',
                    sprintf('%d-%d-%d 12:00:00', $targetYear, $targetMonth, $targetDay),
                    $source->getTimezone(),
                ) ?: throw new \LogicException('A validated target calendar date could not be constructed.'),
                $source,
            ),
            monthEndRuleUsed: true,
            sourceWasMonthEnd: $sourceWasMonthEnd,
            dayWasClamped: $targetDay !== $sourceDay,
            sourceDay: $sourceDay,
            targetDay: $targetDay,
        );
    }

    private static function daysInMonth(int $year, int $month): int
    {
        return match ($month) {
            2 => self::isLeapYear($year) ? 29 : 28,
            4, 6, 9, 11 => 30,
            default => 31,
        };
    }

    private static function isLeapYear(int $year): bool
    {
        return $year % 400 === 0 || ($year % 4 === 0 && $year % 100 !== 0);
    }

    private static function atDateWithWallTime(
        DateTimeImmutable $dateSource,
        DateTimeImmutable $wallTimeSource,
    ): DateTimeImmutable {
        $value = sprintf(
            '%s %s',
            $dateSource->format('Y-m-d'),
            $wallTimeSource->format('H:i:s.u'),
        );
        $result = DateTimeImmutable::createFromFormat(
            '!Y-m-d H:i:s.u',
            $value,
            $dateSource->getTimezone(),
        );

        return $result ?: throw new \LogicException('A validated local calendar value could not be constructed.');
    }

    private static function assertSupportedYear(int $year): void
    {
        if ($year < 1 || $year > 9999) {
            throw new DateOutOfRange('The calculated deadline falls outside years 0001 through 9999.');
        }
    }
}
