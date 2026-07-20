<?php

declare(strict_types=1);

namespace App\Modules\Households\Models;

use App\Modules\Accounts\Models\User;
use App\Modules\Households\Enums\HouseholdRole;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class HouseholdInvitation extends Model
{
    use HasUlids;

    /** @var list<string> */
    protected $fillable = [
        'household_id',
        'invited_by_user_id',
        'email',
        'role',
        'token_hash',
        'expires_at',
        'accepted_at',
    ];

    /** @var list<string> */
    protected $hidden = ['token_hash'];

    /** @return BelongsTo<Household, $this> */
    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    /** @return BelongsTo<User, $this> */
    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by_user_id');
    }

    public function isPending(): bool
    {
        return $this->accepted_at === null && $this->expires_at->isFuture();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'role' => HouseholdRole::class,
            'expires_at' => 'immutable_datetime',
            'accepted_at' => 'immutable_datetime',
        ];
    }
}
