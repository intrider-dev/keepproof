<?php

declare(strict_types=1);

namespace App\Modules\Households\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class HouseholdResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'is_personal' => $this->is_personal,
            'role' => $this->when($this->pivot !== null, fn (): mixed => $this->pivot->role),
            'member_count' => $this->whenCounted('members'),
            'created_at' => $this->created_at?->toAtomString(),
            'updated_at' => $this->updated_at?->toAtomString(),
        ];
    }
}
