<?php

declare(strict_types=1);

namespace App\Modules\Households\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class HouseholdInvitationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'role' => $this->role->value,
            'expires_at' => $this->expires_at->toAtomString(),
            'accepted_at' => $this->accepted_at?->toAtomString(),
        ];
    }
}
