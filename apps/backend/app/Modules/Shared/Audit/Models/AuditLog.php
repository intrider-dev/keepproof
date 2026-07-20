<?php

declare(strict_types=1);

namespace App\Modules\Shared\Audit\Models;

use App\Modules\Accounts\Models\User;
use App\Modules\Households\Models\Household;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string|null $actor_user_id
 * @property string|null $household_id
 * @property string $action
 * @property string|null $subject_type
 * @property string|null $subject_id
 * @property string|null $correlation_id
 * @property array<string, bool|int|string|null> $metadata
 * @property CarbonImmutable $occurred_at
 * @property-read User|null $actor
 * @property-read Household|null $household
 */
final class AuditLog extends Model
{
    use HasUlids;

    public const null UPDATED_AT = null;

    public const string CREATED_AT = 'occurred_at';

    /** @var list<string> */
    protected $fillable = [
        'actor_user_id',
        'household_id',
        'action',
        'subject_type',
        'subject_id',
        'correlation_id',
        'metadata',
        'occurred_at',
    ];

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    /** @return BelongsTo<Household, $this> */
    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'occurred_at' => 'immutable_datetime',
        ];
    }
}
