<?php

declare(strict_types=1);

namespace KeepProof\DeadlineEngine;

enum PeriodUnit: string
{
    case Days = 'days';
    case Months = 'months';
    case Years = 'years';
}
