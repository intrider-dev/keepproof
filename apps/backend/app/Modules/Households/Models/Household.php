<?php

declare(strict_types=1);

namespace App\Modules\Households\Models;

use App\Modules\Accounts\Models\User;
use App\Modules\Purchases\Models\Purchase;
use Database\Factories\HouseholdFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $name
 * @property string $owner_user_id
 * @property bool $is_personal
 * @property int|null $members_count
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $owner
 * @property-read Collection<int, User> $members
 * @property-read Collection<int, HouseholdMember> $memberships
 * @property-read Collection<int, Purchase> $purchases
 */
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

    /** @return HasMany<Purchase, $this> */
    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class);
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
