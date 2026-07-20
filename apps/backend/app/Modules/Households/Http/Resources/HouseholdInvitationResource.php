<?php

declare(strict_types=1);

namespace App\Modules\Households\Http\Resources;

use App\Modules\Households\Models\HouseholdInvitation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class HouseholdInvitationResource extends JsonResource
{
    /** @var HouseholdInvitation */
    public $resource;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $invitation = $this->resource;

        return [
            'id' => $invitation->id,
            'email' => $invitation->email,
            'role' => $invitation->role->value,
            'expires_at' => $invitation->expires_at->toAtomString(),
            'accepted_at' => $invitation->accepted_at?->toAtomString(),
        ];
    }
}
