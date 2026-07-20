<?php

declare(strict_types=1);

namespace KeepProof\DeadlineEngine;

enum DeadlineKind: string
{
    case Return = 'return';
    case Warranty = 'warranty';
}
