<?php

declare(strict_types=1);

namespace App\Modules\Documents\Enums;

enum DocumentState: string
{
    case Uploaded = 'uploaded';
    case ValidationPending = 'validation_pending';
    case Processing = 'processing';
    case NeedsReview = 'needs_review';
    case Ready = 'ready';
    case Failed = 'failed';
    case Quarantined = 'quarantined';
}
