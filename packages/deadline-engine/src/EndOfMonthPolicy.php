<?php

declare(strict_types=1);

namespace KeepProof\DeadlineEngine;

enum EndOfMonthPolicy: string
{
    /** Cap an unavailable ordinal day at the target month's final day. */
    case Clamp = 'clamp';

    /** Keep a source month-end on the target month-end; otherwise clamp. */
    case PreserveEndOfMonth = 'preserve_end_of_month';
}
