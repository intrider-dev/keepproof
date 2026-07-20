<?php

declare(strict_types=1);

namespace App\Modules\Purchases\Http\Resources;

use App\Modules\Purchases\Models\PurchaseDeadline;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class PurchaseDeadlineResource extends JsonResource
{
    /** @var PurchaseDeadline */
    public $resource;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'kind' => $this->resource->kind->value,
            'due_at' => $this->resource->due_at->toAtomString(),
            'due_on' => $this->resource->due_at
                ->setTimezone($this->resource->local_timezone)
                ->format('Y-m-d'),
            'timezone' => $this->resource->local_timezone,
            'source' => $this->resource->source->value,
            'rule_summary' => $this->resource->rule_summary,
            'trace' => $this->resource->trace,
            'reminder_intervals' => $this->resource->reminder_intervals,
            'active' => $this->resource->active,
        ];
    }
}
