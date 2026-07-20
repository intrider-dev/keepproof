<?php

declare(strict_types=1);

namespace App\Modules\Households\Models;

use App\Modules\Accounts\Models\User;
use Database\Factories\HouseholdFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Household extends Model
{
    /** @use HasFactory<HouseholdFactory> */
    use HasFactory;

    use HasUlids;

    /** @var list<string> */
    protected $fillable = ['name', 'owner_user_id', 'is_personal'];

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    /** @return HasMany<HouseholdMember, $this> */
    public function memberships(): HasMany
    {
        return $this->hasMany(HouseholdMember::class);
    }

    /** @return BelongsToMany<User, $this> */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'household_members')
            ->withPivot(['id', 'role', 'joined_at'])
            ->withTimestamps();
    }

    /** @return HasMany<HouseholdInvitation, $this> */
    public function invitations(): HasMany
    {
        return $this->hasMany(HouseholdInvitation::class);
    }

    protected static function newFactory(): HouseholdFactory
    {
        return HouseholdFactory::new();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_personal' => 'boolean'];
    }
}
