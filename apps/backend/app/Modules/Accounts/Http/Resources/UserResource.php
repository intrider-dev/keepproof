<?php

declare(strict_types=1);

namespace App\Modules\Accounts\Http\Resources;

use App\Modules\Accounts\Models\User;
use App\Modules\Households\Http\Resources\HouseholdResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class UserResource extends JsonResource
{
    /** @var User */
    public $resource;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $user = $this->resource;

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'email_verified' => $user->hasVerifiedEmail(),
            'timezone' => $user->timezone,
            'locale' => $user->locale,
            'households' => HouseholdResource::collection($this->whenLoaded('households')),
            'created_at' => $user->created_at?->toAtomString(),
        ];
    }
}
