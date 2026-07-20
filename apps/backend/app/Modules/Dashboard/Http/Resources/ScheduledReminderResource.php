<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Http\Resources;

use App\Modules\Notifications\Models\Reminder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class ScheduledReminderResource extends JsonResource
{
    /** @var Reminder */
    public $resource;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $reminder = $this->resource;

        return [
            'id' => $reminder->id,
            'channel' => $reminder->channel->value,
            'status' => $reminder->status->value,
            'interval_days' => $reminder->interval_days,
            'remind_at' => $reminder->remind_at->toAtomString(),
            'deadline' => [
                'id' => $reminder->deadline->id,
                'kind' => $reminder->deadline->kind->value,
                'purchase_id' => $reminder->deadline->purchase_id,
                'merchant' => $reminder->deadline->purchase->merchant,
            ],
        ];
    }
}
