<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Enums;

enum ReminderStatus: string
{
    case Scheduled = 'scheduled';
    case Sending = 'sending';
    case Sent = 'sent';
    case DeliveryUnknown = 'delivery_unknown';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
}
