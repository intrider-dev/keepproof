<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Http\Resources;

use App\Modules\Notifications\Models\NotificationPreference;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class NotificationPreferenceResource extends JsonResource
{
    /** @var NotificationPreference */
    public $resource;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'in_app_enabled' => $this->resource->in_app_enabled,
            'email_enabled' => $this->resource->email_enabled,
            'return_intervals' => $this->resource->return_intervals,
            'warranty_intervals' => $this->resource->warranty_intervals,
            'quiet_hours_start' => $this->resource->quiet_hours_start,
            'quiet_hours_end' => $this->resource->quiet_hours_end,
            'timezone' => $this->resource->user->timezone,
        ];
    }
}
