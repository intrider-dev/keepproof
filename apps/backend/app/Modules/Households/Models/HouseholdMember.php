<?php

declare(strict_types=1);

namespace App\Modules\Households\Models;

use App\Modules\Accounts\Models\User;
use App\Modules\Households\Enums\HouseholdRole;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $household_id
 * @property string $user_id
 * @property HouseholdRole $role
 * @property CarbonImmutable $joined_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Household $household
 * @property-read User $user
 */
final class HouseholdMember extends Model
{
    use HasUlids;

    /** @var list<string> */
    protected $fillable = ['household_id', 'user_id', 'role', 'joined_at'];

    /** @return BelongsTo<Household, $this> */
    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'role' => HouseholdRole::class,
            'joined_at' => 'immutable_datetime',
        ];
    }
}
