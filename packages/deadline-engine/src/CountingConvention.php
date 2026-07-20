<?php

declare(strict_types=1);

namespace KeepProof\DeadlineEngine;

enum CountingConvention: string
{
    /** The purchase calendar day is day one of the period. */
    case PurchaseDayInclusive = 'purchase_day_inclusive';

    /** Counting starts after the purchase calendar day. */
    case PurchaseDayExclusive = 'purchase_day_exclusive';
}
