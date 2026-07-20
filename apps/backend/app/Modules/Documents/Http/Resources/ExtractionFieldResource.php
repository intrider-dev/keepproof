<?php

declare(strict_types=1);

namespace App\Modules\Documents\Http\Resources;

use App\Modules\Documents\Models\ExtractionField;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class ExtractionFieldResource extends JsonResource
{
    /** @var ExtractionField */
    public $resource;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'key' => $this->resource->field_key,
            'value' => $this->resource->raw_value,
            'normalized_value' => $this->resource->normalized_value,
            'confidence' => $this->resource->confidence,
            'source' => $this->resource->source,
            'validation_errors' => $this->resource->validation_errors,
            'confirmed_value' => $this->resource->confirmed_value,
        ];
    }
}
