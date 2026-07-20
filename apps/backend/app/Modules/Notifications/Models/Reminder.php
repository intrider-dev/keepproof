<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Models;

use App\Modules\Accounts\Models\User;
use App\Modules\Households\Models\Household;
use App\Modules\Notifications\Enums\ReminderChannel;
use App\Modules\Notifications\Enums\ReminderStatus;
use App\Modules\Purchases\Models\PurchaseDeadline;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $purchase_deadline_id
 * @property string $household_id
 * @property string $user_id
 * @property ReminderChannel $channel
 * @property int $interval_days
 * @property CarbonImmutable $remind_at
 * @property ReminderStatus $status
 * @property string $deduplication_key
 * @property int $attempts
 * @property CarbonImmutable|null $sent_at
 * @property string|null $last_error_code
 * @property-read PurchaseDeadline $deadline
 * @property-read Household $household
 * @property-read User $user
 */
final class Reminder extends Model
{
    use HasUlids;

    /** @var list<string> */
    protected $fillable = [
        'purchase_deadline_id',
        'household_id',
        'user_id',
        'channel',
        'interval_days',
        'remind_at',
        'status',
        'deduplication_key',
        'attempts',
        'sent_at',
        'last_error_code',
    ];

    /** @return BelongsTo<PurchaseDeadline, $this> */
    public function deadline(): BelongsTo
    {
        return $this->belongsTo(PurchaseDeadline::class, 'purchase_deadline_id');
    }

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
            'channel' => ReminderChannel::class,
            'interval_days' => 'integer',
            'remind_at' => 'immutable_datetime',
            'status' => ReminderStatus::class,
            'attempts' => 'integer',
            'sent_at' => 'immutable_datetime',
        ];
    }
}
