<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Http\Resources;

use App\Modules\Purchases\Models\PurchaseDeadline;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class UpcomingDeadlineResource extends JsonResource
{
    /** @var PurchaseDeadline */
    public $resource;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $deadline = $this->resource;

        return [
            'id' => $deadline->id,
            'kind' => $deadline->kind->value,
            'due_at' => $deadline->due_at->toAtomString(),
            'due_on' => $deadline->due_at->setTimezone($deadline->local_timezone)->format('Y-m-d'),
            'timezone' => $deadline->local_timezone,
            'rule_summary' => $deadline->rule_summary,
            'source' => $deadline->source->value,
            'purchase' => [
                'id' => $deadline->purchase->id,
                'merchant' => $deadline->purchase->merchant,
                'currency' => $deadline->purchase->currency,
                'total_minor' => $deadline->purchase->total_minor,
                'item_names' => $deadline->purchase->items->pluck('name')->values()->all(),
            ],
        ];
    }
}
