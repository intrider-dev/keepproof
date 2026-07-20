<?php

declare(strict_types=1);

namespace App\Modules\Purchases\Http\Resources;

use App\Modules\Purchases\Models\Purchase;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class PurchaseResource extends JsonResource
{
    /** @var Purchase */
    public $resource;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $purchase = $this->resource;

        return [
            'id' => $purchase->id,
            'household_id' => $purchase->household_id,
            'state' => $purchase->state->value,
            'merchant' => $purchase->merchant,
            'purchase_date' => $purchase->purchase_date->format('Y-m-d'),
            'timezone' => $purchase->timezone,
            'receipt_number' => $purchase->receipt_number,
            'currency' => $purchase->currency,
            'total_minor' => $purchase->total_minor,
            'country_region' => $purchase->country_region,
            'return_terms' => $this->terms(
                $purchase->return_period_quantity,
                $purchase->return_period_unit?->value,
                $purchase->return_counting_convention->value,
            ),
            'warranty_terms' => $this->terms(
                $purchase->warranty_period_quantity,
                $purchase->warranty_period_unit?->value,
                $purchase->warranty_counting_convention->value,
            ),
            'manual_return_deadline_at' => $purchase->manual_return_deadline_at?->toAtomString(),
            'manual_return_reason' => $purchase->manual_return_reason,
            'manual_warranty_deadline_at' => $purchase->manual_warranty_deadline_at?->toAtomString(),
            'manual_warranty_reason' => $purchase->manual_warranty_reason,
            'notes' => $purchase->notes,
            'items' => PurchaseItemResource::collection($this->whenLoaded('items')),
            'deadlines' => PurchaseDeadlineResource::collection($this->whenLoaded('deadlines')),
            'activated_at' => $purchase->activated_at?->toAtomString(),
            'created_at' => $purchase->created_at?->toAtomString(),
            'updated_at' => $purchase->updated_at?->toAtomString(),
        ];
    }

    /** @return array{quantity: int, unit: string, counting_convention: string}|null */
    private function terms(?int $quantity, ?string $unit, string $countingConvention): ?array
    {
        if ($quantity === null || $unit === null) {
            return null;
        }

        return [
            'quantity' => $quantity,
            'unit' => $unit,
            'counting_convention' => $countingConvention,
        ];
    }
}
