<?php

declare(strict_types=1);

namespace KeepProof\DeadlineEngine\Value;

use KeepProof\DeadlineEngine\CountingConvention;
use KeepProof\DeadlineEngine\EndOfMonthPolicy;

final readonly class DeadlineTerms
{
    public function __construct(
        public Period $period,
        public CountingConvention $countingConvention = CountingConvention::PurchaseDayExclusive,
        public EndOfMonthPolicy $endOfMonthPolicy = EndOfMonthPolicy::Clamp,
        public ?ManualOverride $manualOverride = null,
    ) {
    }
}
