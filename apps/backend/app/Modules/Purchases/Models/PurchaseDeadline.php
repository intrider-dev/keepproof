<?php

declare(strict_types=1);

namespace App\Modules\Purchases\Models;

use App\Modules\Households\Models\Household;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use KeepProof\DeadlineEngine\DeadlineKind;
use KeepProof\DeadlineEngine\DeadlineSource;

/**
 * @property string $id
 * @property string $purchase_id
 * @property string $household_id
 * @property DeadlineKind $kind
 * @property CarbonImmutable $due_at
 * @property string $local_timezone
 * @property DeadlineSource $source
 * @property string $rule_summary
 * @property list<array<string, mixed>> $trace
 * @property list<int> $reminder_intervals
 * @property bool $active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Purchase $purchase
 * @property-read Household $household
 */
final class PurchaseDeadline extends Model
{
    use HasUlids;

    /** @var list<string> */
    protected $fillable = [
        'purchase_id',
        'household_id',
        'kind',
        'due_at',
        'local_timezone',
        'source',
        'rule_summary',
        'trace',
        'reminder_intervals',
        'active',
    ];

    /** @return BelongsTo<Purchase, $this> */
    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
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
            'kind' => DeadlineKind::class,
            'due_at' => 'immutable_datetime',
            'source' => DeadlineSource::class,
            'trace' => 'array',
            'reminder_intervals' => 'array',
            'active' => 'boolean',
        ];
    }
}
