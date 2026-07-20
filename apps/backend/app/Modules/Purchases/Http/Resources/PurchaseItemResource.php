<?php

declare(strict_types=1);

namespace App\Modules\Purchases\Http\Resources;

use App\Modules\Purchases\Models\PurchaseItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class PurchaseItemResource extends JsonResource
{
    /** @var PurchaseItem */
    public $resource;

    /** @return array<string, int|string|null> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'quantity' => $this->resource->quantity,
            'unit_price_minor' => $this->resource->unit_price_minor,
            'serial_number' => $this->resource->serial_number,
        ];
    }
}
