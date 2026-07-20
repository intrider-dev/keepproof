<?php

declare(strict_types=1);

namespace App\Modules\Shared\Idempotency;

use App\Modules\Accounts\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $user_id
 * @property string $operation
 * @property string $key_hash
 * @property string $resource_type
 * @property string $resource_id
 * @property CarbonImmutable $expires_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 */
final class IdempotencyRecord extends Model
{
    use HasUlids;

    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'operation',
        'key_hash',
        'resource_type',
        'resource_id',
        'expires_at',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['expires_at' => 'immutable_datetime'];
    }
}
