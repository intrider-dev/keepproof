<?php

declare(strict_types=1);

namespace App\Modules\Households\Http\Resources;

use App\Modules\Households\Models\HouseholdMember;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class HouseholdMemberResource extends JsonResource
{
    /** @var HouseholdMember */
    public $resource;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $membership = $this->resource;

        return [
            'id' => $membership->id,
            'role' => $membership->role->value,
            'joined_at' => $membership->joined_at->toAtomString(),
            'user' => [
                'id' => $membership->user->id,
                'name' => $membership->user->name,
                'email' => $membership->user->email,
            ],
        ];
    }
}
