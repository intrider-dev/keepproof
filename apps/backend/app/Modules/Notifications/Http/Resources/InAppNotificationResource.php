<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Notifications\DatabaseNotification;

final class InAppNotificationResource extends JsonResource
{
    /** @var DatabaseNotification */
    public $resource;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'data' => $this->resource->data,
            'read_at' => $this->resource->read_at?->toAtomString(),
            'created_at' => $this->resource->created_at?->toAtomString(),
        ];
    }
}
