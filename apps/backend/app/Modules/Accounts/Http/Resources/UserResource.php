<?php

declare(strict_types=1);

namespace App\Modules\Accounts\Http\Resources;

use App\Modules\Households\Http\Resources\HouseholdResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class UserResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'email_verified' => $this->hasVerifiedEmail(),
            'timezone' => $this->timezone,
            'locale' => $this->locale,
            'households' => HouseholdResource::collection($this->whenLoaded('households')),
            'created_at' => $this->created_at?->toAtomString(),
        ];
    }
}
