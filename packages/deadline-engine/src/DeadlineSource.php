<?php

declare(strict_types=1);

namespace KeepProof\DeadlineEngine;

enum DeadlineSource: string
{
    case Calculated = 'calculated';
    case ManualOverride = 'manual_override';
}
