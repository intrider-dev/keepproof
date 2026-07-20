<?php

declare(strict_types=1);

namespace App\Modules\Documents\Http\Resources;

use App\Modules\Documents\Models\ReceiptDocument;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class PurchaseDocumentResource extends JsonResource
{
    /** @var ReceiptDocument */
    public $resource;

    /** @return array<string, bool|int|string|null> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'state' => $this->resource->state->value,
            'filename' => $this->resource->original_filename,
            'mime_type' => $this->resource->mime_type,
            'size_bytes' => $this->resource->size_bytes,
            'retain_original' => $this->resource->retain_original,
            'processed_at' => $this->resource->processed_at?->toAtomString(),
        ];
    }
}
