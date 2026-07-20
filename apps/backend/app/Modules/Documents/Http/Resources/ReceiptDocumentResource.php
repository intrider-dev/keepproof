<?php

declare(strict_types=1);

namespace App\Modules\Documents\Http\Resources;

use App\Modules\Documents\Models\ReceiptDocument;
use App\Modules\Purchases\Http\Resources\PurchaseResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class ReceiptDocumentResource extends JsonResource
{
    /** @var ReceiptDocument */
    public $resource;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $document = $this->resource;

        return [
            'id' => $document->id,
            'purchase_id' => $document->purchase_id,
            'household_id' => $document->household_id,
            'state' => $document->state->value,
            'filename' => $document->original_filename,
            'mime_type' => $document->mime_type,
            'size_bytes' => $document->size_bytes,
            'processing_attempts' => $document->processing_attempts,
            'ocr_provider' => $document->ocr_provider,
            'extraction_provider' => $document->extraction_provider,
            'prompt_version' => $document->prompt_version,
            'user_error' => $document->user_error,
            'retain_original' => $document->retain_original,
            'fields' => ExtractionFieldResource::collection($this->whenLoaded('fields')),
            'purchase' => new PurchaseResource($this->whenLoaded('purchase')),
            'processed_at' => $document->processed_at?->toAtomString(),
            'created_at' => $document->created_at?->toAtomString(),
            'updated_at' => $document->updated_at?->toAtomString(),
        ];
    }
}
