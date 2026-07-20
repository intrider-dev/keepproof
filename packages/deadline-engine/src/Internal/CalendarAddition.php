<?php

declare(strict_types=1);

namespace KeepProof\DeadlineEngine\Internal;

use DateTimeImmutable;

/** @internal */
final readonly class CalendarAddition
{
    public function __construct(
        public DateTimeImmutable $dateTime,
        public bool $monthEndRuleUsed,
        public bool $sourceWasMonthEnd,
        public bool $dayWasClamped,
        public int $sourceDay,
        public int $targetDay,
    ) {
    }
}
