<?php

declare(strict_types=1);

namespace KeepProof\DeadlineEngine;

enum RuleCode: string
{
    case PurchaseMoment = 'purchase_moment';
    case PeriodApplied = 'period_applied';
    case PurchaseDayIncluded = 'purchase_day_included';
    case PurchaseDayExcluded = 'purchase_day_excluded';
    case EndOfMonthResolved = 'end_of_month_resolved';
    case TimezoneOffsetChanged = 'timezone_offset_changed';
    case DstGapResolved = 'dst_gap_resolved';
    case ManualOverrideApplied = 'manual_override_applied';
}
