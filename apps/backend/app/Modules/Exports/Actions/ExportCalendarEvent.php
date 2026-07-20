<?php

declare(strict_types=1);

namespace App\Modules\Exports\Actions;

use App\Modules\Purchases\Models\PurchaseDeadline;

final class ExportCalendarEvent
{
    public function execute(PurchaseDeadline $deadline): string
    {
        $deadline->loadMissing('purchase.items');
        $purchase = $deadline->purchase;
        $kind = $deadline->kind->value === 'return' ? 'Return window' : 'Warranty';
        $itemNames = $purchase->items->pluck('name')->implode(', ');
        $description = sprintf(
            '%s for %s. %s KeepProof records supplied terms and does not provide legal advice.',
            $kind,
            $purchase->merchant,
            $itemNames === '' ? '' : 'Items: '.$itemNames.'.',
        );
        $startsAt = $deadline->due_at->utc();
        $endsAt = $startsAt->addHour();

        return implode("\r\n", [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//KeepProof//Deadline Export//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            'UID:'.$deadline->id.'@keepproof.local',
            'DTSTAMP:'.now()->utc()->format('Ymd\THis\Z'),
            'DTSTART:'.$startsAt->format('Ymd\THis\Z'),
            'DTEND:'.$endsAt->format('Ymd\THis\Z'),
            'SUMMARY:'.$this->escape($kind.' — '.$purchase->merchant),
            'DESCRIPTION:'.$this->escape(trim($description)),
            'END:VEVENT',
            'END:VCALENDAR',
            '',
        ]);
    }

    private function escape(string $value): string
    {
        return str_replace(
            ['\\', "\r\n", "\n", ',', ';'],
            ['\\\\', '\\n', '\\n', '\\,', '\\;'],
            $value,
        );
    }
}
