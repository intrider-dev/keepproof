<?php

declare(strict_types=1);

namespace App\Modules\Accounts\Models;

use App\Modules\Households\Models\Household;
use App\Modules\Households\Models\HouseholdMember;
use Database\Factories\UserFactory;
use Illuminate\Auth\MustVerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail as MustVerifyEmailContract;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

final class User extends Authenticatable implements MustVerifyEmailContract
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use HasUlids;
    use MustVerifyEmail;
    use Notifiable;

    /** @var list<string> */
    protected $fillable = [
        'name',
        'email',
        'password',
        'timezone',
        'locale',
    ];

    /** @var list<string> */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /** @return BelongsToMany<Household, $this> */
    public function households(): BelongsToMany
    {
        return $this->belongsToMany(Household::class, 'household_members')
            ->withPivot(['id', 'role', 'joined_at'])
            ->withTimestamps();
    }

    /** @return HasMany<HouseholdMember, $this> */
    public function householdMemberships(): HasMany
    {
        return $this->hasMany(HouseholdMember::class);
    }

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }

    /** @return Attribute<string, string> */
    protected function email(): Attribute
    {
        return Attribute::make(
            set: static fn (string $value): string => Str::lower(trim($value)),
        );
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'immutable_datetime',
            'password' => 'hashed',
        ];
    }
}
