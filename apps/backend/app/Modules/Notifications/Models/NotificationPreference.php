<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Models;

use App\Modules\Accounts\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $user_id
 * @property bool $in_app_enabled
 * @property bool $email_enabled
 * @property list<int> $return_intervals
 * @property list<int> $warranty_intervals
 * @property string|null $quiet_hours_start
 * @property string|null $quiet_hours_end
 * @property-read User $user
 */
final class NotificationPreference extends Model
{
    use HasUlids;

    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'in_app_enabled',
        'email_enabled',
        'return_intervals',
        'warranty_intervals',
        'quiet_hours_start',
        'quiet_hours_end',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'in_app_enabled' => 'boolean',
            'email_enabled' => 'boolean',
            'return_intervals' => 'array',
            'warranty_intervals' => 'array',
        ];
    }
}
