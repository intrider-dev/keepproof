<?php

declare(strict_types=1);

namespace App\Modules\Purchases\Models;

use App\Modules\Accounts\Models\User;
use App\Modules\Households\Models\Household;
use App\Modules\Purchases\Enums\PurchaseState;
use Carbon\CarbonImmutable;
use Database\Factories\PurchaseFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use KeepProof\DeadlineEngine\CountingConvention;
use KeepProof\DeadlineEngine\PeriodUnit;

/**
 * @property string $id
 * @property string $household_id
 * @property string|null $created_by_user_id
 * @property PurchaseState $state
 * @property string $merchant
 * @property CarbonImmutable $purchase_date
 * @property string $timezone
 * @property string|null $receipt_number
 * @property string $currency
 * @property int $total_minor
 * @property string $country_region
 * @property int|null $return_period_quantity
 * @property PeriodUnit|null $return_period_unit
 * @property CountingConvention $return_counting_convention
 * @property int|null $warranty_period_quantity
 * @property PeriodUnit|null $warranty_period_unit
 * @property CountingConvention $warranty_counting_convention
 * @property CarbonImmutable|null $manual_return_deadline_at
 * @property string|null $manual_return_reason
 * @property CarbonImmutable|null $manual_warranty_deadline_at
 * @property string|null $manual_warranty_reason
 * @property string|null $notes
 * @property CarbonImmutable|null $activated_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Household $household
 * @property-read User|null $creator
 * @property-read Collection<int, PurchaseItem> $items
 * @property-read Collection<int, PurchaseDeadline> $deadlines
 */
final class Purchase extends Model
{
    /** @use HasFactory<PurchaseFactory> */
    use HasFactory;

    use HasUlids;

    /** @var list<string> */
    protected $fillable = [
        'household_id',
        'created_by_user_id',
        'state',
        'merchant',
        'purchase_date',
        'timezone',
        'receipt_number',
        'currency',
        'total_minor',
        'country_region',
        'return_period_quantity',
        'return_period_unit',
        'return_counting_convention',
        'warranty_period_quantity',
        'warranty_period_unit',
        'warranty_counting_convention',
        'manual_return_deadline_at',
        'manual_return_reason',
        'manual_warranty_deadline_at',
        'manual_warranty_reason',
        'notes',
        'activated_at',
    ];

    /** @return BelongsTo<Household, $this> */
    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /** @return HasMany<PurchaseItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }

    /** @return HasMany<PurchaseDeadline, $this> */
    public function deadlines(): HasMany
    {
        return $this->hasMany(PurchaseDeadline::class);
    }

    protected static function newFactory(): PurchaseFactory
    {
        return PurchaseFactory::new();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'state' => PurchaseState::class,
            'purchase_date' => 'immutable_date',
            'total_minor' => 'integer',
            'return_period_quantity' => 'integer',
            'return_period_unit' => PeriodUnit::class,
            'return_counting_convention' => CountingConvention::class,
            'warranty_period_quantity' => 'integer',
            'warranty_period_unit' => PeriodUnit::class,
            'warranty_counting_convention' => CountingConvention::class,
            'manual_return_deadline_at' => 'immutable_datetime',
            'manual_warranty_deadline_at' => 'immutable_datetime',
            'activated_at' => 'immutable_datetime',
        ];
    }
}
