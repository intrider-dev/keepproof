<?php

declare(strict_types=1);

namespace App\Modules\Households\Http\Resources;

use App\Modules\Households\Enums\HouseholdRole;
use App\Modules\Households\Models\Household;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class HouseholdResource extends JsonResource
{
    /** @var Household */
    public $resource;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $household = $this->resource;
        $membershipRole = $this->membershipRole($household);

        return [
            'id' => $household->id,
            'name' => $household->name,
            'is_personal' => $household->is_personal,
            'role' => $this->when($membershipRole !== null, $membershipRole),
            'member_count' => $this->whenCounted('members'),
            'created_at' => $household->created_at?->toAtomString(),
            'updated_at' => $household->updated_at?->toAtomString(),
        ];
    }

    private function membershipRole(Household $household): ?string
    {
        if (! $household->relationLoaded('pivot')) {
            return null;
        }

        $pivot = $household->getRelation('pivot');

        if (! $pivot instanceof Pivot) {
            return null;
        }

        $role = $pivot->getAttribute('role');

        return is_string($role) ? HouseholdRole::tryFrom($role)?->value : null;
    }
}
