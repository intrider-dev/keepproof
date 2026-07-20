<?php

declare(strict_types=1);

namespace KeepProof\DeadlineEngine;

use DateTimeImmutable;
use KeepProof\DeadlineEngine\Exception\InvalidManualOverride;
use KeepProof\DeadlineEngine\Internal\CalendarAddition;
use KeepProof\DeadlineEngine\Internal\CalendarMath;
use KeepProof\DeadlineEngine\Value\DeadlineResult;
use KeepProof\DeadlineEngine\Value\DeadlineTerms;
use KeepProof\DeadlineEngine\Value\LocalDateTime;
use KeepProof\DeadlineEngine\Value\PurchaseDeadlineRequest;
use KeepProof\DeadlineEngine\Value\PurchaseDeadlineResult;
use KeepProof\DeadlineEngine\Value\RuleTraceStep;

final class DeadlineCalculator
{
    public function calculate(PurchaseDeadlineRequest $request): PurchaseDeadlineResult
    {
        return new PurchaseDeadlineResult(
            returnDeadline: $request->returnTerms === null
                ? null
                : $this->calculateOne(DeadlineKind::Return, $request->purchasedAt, $request->returnTerms),
            warrantyDeadline: $request->warrantyTerms === null
                ? null
                : $this->calculateOne(DeadlineKind::Warranty, $request->purchasedAt, $request->warrantyTerms),
        );
    }

    private function calculateOne(
        DeadlineKind $kind,
        LocalDateTime $purchasedAt,
        DeadlineTerms $terms,
    ): DeadlineResult {
        $purchase = $purchasedAt->dateTime();
        $calendarAddition = $this->calculateCalendarBoundary($purchase, $terms);
        $calculatedAt = $this->applyCountingConvention($purchase, $calendarAddition, $terms);
        $calculatedDeadline = LocalDateTime::fromDateTime($calculatedAt);
        $trace = $this->baseTrace($purchasedAt, $terms, $calendarAddition, $calculatedAt);
        $effectiveDeadline = $calculatedDeadline;
        $source = DeadlineSource::Calculated;

        if ($terms->manualOverride !== null) {
            if ($terms->manualOverride->deadline->isBefore($purchasedAt)) {
                throw new InvalidManualOverride(sprintf(
                    'The %s deadline override cannot be earlier than the purchase moment.',
                    $kind->value,
                ));
            }

            $effectiveDeadline = $terms->manualOverride->deadline->inTimezone($purchasedAt->timezone());
            $source = DeadlineSource::ManualOverride;
            $trace[] = new RuleTraceStep(
                RuleCode::ManualOverrideApplied,
                'A user-supplied deadline replaced the calculated deadline.',
                [
                    'calculated_deadline' => $calculatedDeadline->toRfc3339(),
                    'override_deadline' => $effectiveDeadline->toRfc3339(),
                    'reason' => $terms->manualOverride->reason,
                ],
            );
        }

        return new DeadlineResult(
            kind: $kind,
            effectiveDeadline: $effectiveDeadline,
            calculatedDeadline: $calculatedDeadline,
            source: $source,
            ruleSummary: $this->summary($terms),
            trace: $trace,
        );
    }

    private function calculateCalendarBoundary(
        DateTimeImmutable $purchase,
        DeadlineTerms $terms,
    ): CalendarAddition {
        if (
            $terms->countingConvention === CountingConvention::PurchaseDayInclusive
            && $terms->period->unit === PeriodUnit::Days
        ) {
            return CalendarMath::addDays($purchase, $terms->period->quantity - 1);
        }

        return CalendarMath::add($purchase, $terms->period, $terms->endOfMonthPolicy);
    }

    private function applyCountingConvention(
        DateTimeImmutable $purchase,
        CalendarAddition $addition,
        DeadlineTerms $terms,
    ): DateTimeImmutable {
        if ($terms->countingConvention === CountingConvention::PurchaseDayExclusive) {
            return $addition->dateTime;
        }

        if ($terms->period->unit === PeriodUnit::Days) {
            return $addition->dateTime;
        }

        return CalendarMath::previousDayKeepingWallTime($addition->dateTime, $purchase);
    }

    /** @return non-empty-list<RuleTraceStep> */
    private function baseTrace(
        LocalDateTime $purchasedAt,
        DeadlineTerms $terms,
        CalendarAddition $addition,
        DateTimeImmutable $calculatedAt,
    ): array {
        $purchase = $purchasedAt->dateTime();
        $trace = [
            new RuleTraceStep(
                RuleCode::PurchaseMoment,
                'The purchase instant was resolved in its IANA timezone.',
                [
                    'purchased_at' => $purchasedAt->toRfc3339(),
                    'timezone' => $purchasedAt->timezone(),
                ],
            ),
            new RuleTraceStep(
                RuleCode::PeriodApplied,
                sprintf('A period of %d calendar %s was applied.', $terms->period->quantity, $terms->period->unit->value),
                [
                    'quantity' => $terms->period->quantity,
                    'unit' => $terms->period->unit->value,
                ],
            ),
            $terms->countingConvention === CountingConvention::PurchaseDayInclusive
                ? new RuleTraceStep(
                    RuleCode::PurchaseDayIncluded,
                    'The purchase calendar day counted as the first day of the period.',
                )
                : new RuleTraceStep(
                    RuleCode::PurchaseDayExcluded,
                    'Counting began after the purchase calendar day.',
                ),
        ];

        if ($addition->monthEndRuleUsed) {
            $trace[] = new RuleTraceStep(
                RuleCode::EndOfMonthResolved,
                $this->monthEndExplanation($terms, $addition),
                [
                    'policy' => $terms->endOfMonthPolicy->value,
                    'source_was_month_end' => $addition->sourceWasMonthEnd,
                    'source_day' => $addition->sourceDay,
                    'target_day' => $addition->targetDay,
                    'day_was_clamped' => $addition->dayWasClamped,
                ],
            );
        }

        if ($purchase->format('P') !== $calculatedAt->format('P')) {
            $trace[] = new RuleTraceStep(
                RuleCode::TimezoneOffsetChanged,
                'The UTC offset changed while calendar arithmetic preserved local-time semantics.',
                [
                    'purchase_offset' => $purchase->format('P'),
                    'deadline_offset' => $calculatedAt->format('P'),
                ],
            );
        }

        if ($purchase->format('H:i:s.u') !== $calculatedAt->format('H:i:s.u')) {
            $trace[] = new RuleTraceStep(
                RuleCode::DstGapResolved,
                'The intended wall time did not exist at the destination and was moved forward by the timezone transition.',
                [
                    'requested_wall_time' => $purchase->format('H:i:s.u'),
                    'resolved_wall_time' => $calculatedAt->format('H:i:s.u'),
                ],
            );
        }

        return $trace;
    }

    private function summary(DeadlineTerms $terms): string
    {
        $counting = $terms->countingConvention === CountingConvention::PurchaseDayInclusive
            ? 'purchase day included'
            : 'purchase day excluded';
        $override = $terms->manualOverride === null ? '' : '; manual override applied';
        $monthEnd = $terms->period->unit === PeriodUnit::Days
            ? ''
            : sprintf('; month-end policy: %s', $terms->endOfMonthPolicy->value);

        return sprintf(
            '%d calendar %s; %s%s%s.',
            $terms->period->quantity,
            $terms->period->unit->value,
            $counting,
            $monthEnd,
            $override,
        );
    }

    private function monthEndExplanation(
        DeadlineTerms $terms,
        CalendarAddition $addition,
    ): string {
        if ($terms->endOfMonthPolicy === EndOfMonthPolicy::PreserveEndOfMonth && $addition->sourceWasMonthEnd) {
            return 'The source month-end was preserved in the target month.';
        }

        if ($addition->dayWasClamped) {
            return 'The unavailable ordinal day was clamped to the target month end.';
        }

        return 'The ordinal day existed in the target month and was retained.';
    }
}
