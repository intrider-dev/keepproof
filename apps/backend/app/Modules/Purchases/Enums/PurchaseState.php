<?php

declare(strict_types=1);

namespace App\Modules\Purchases\Enums;

enum PurchaseState: string
{
    case Draft = 'draft';
    case NeedsReview = 'needs_review';
    case Active = 'active';
    case Archived = 'archived';
}
