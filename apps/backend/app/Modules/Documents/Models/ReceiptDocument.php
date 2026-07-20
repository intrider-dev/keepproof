<?php

declare(strict_types=1);

namespace App\Modules\Documents\Models;

use App\Modules\Accounts\Models\User;
use App\Modules\Documents\Enums\DocumentState;
use App\Modules\Households\Models\Household;
use App\Modules\Purchases\Models\Purchase;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $purchase_id
 * @property string $household_id
 * @property string|null $uploaded_by_user_id
 * @property DocumentState $state
 * @property string $storage_disk
 * @property string $storage_key
 * @property string $original_filename
 * @property string $mime_type
 * @property int $size_bytes
 * @property string $sha256
 * @property string|null $raw_ocr_text
 * @property string|null $ocr_provider
 * @property string|null $extraction_provider
 * @property string|null $prompt_version
 * @property int $processing_attempts
 * @property CarbonImmutable|null $processing_dispatched_at
 * @property string $correlation_id
 * @property string|null $last_error_code
 * @property string|null $user_error
 * @property bool $retain_original
 * @property CarbonImmutable|null $processed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Purchase $purchase
 * @property-read Household $household
 * @property-read User|null $uploader
 * @property-read Collection<int, ExtractionField> $fields
 * @property-read Collection<int, DocumentStateTransition> $transitions
 */
final class ReceiptDocument extends Model
{
    use HasUlids;

    /** @var list<string> */
    protected $fillable = [
        'id',
        'purchase_id',
        'household_id',
        'uploaded_by_user_id',
        'state',
        'storage_disk',
        'storage_key',
        'original_filename',
        'mime_type',
        'size_bytes',
        'sha256',
        'raw_ocr_text',
        'ocr_provider',
        'extraction_provider',
        'prompt_version',
        'processing_attempts',
        'processing_dispatched_at',
        'correlation_id',
        'last_error_code',
        'user_error',
        'retain_original',
        'processed_at',
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

    /** @return BelongsTo<User, $this> */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    /** @return HasMany<ExtractionField, $this> */
    public function fields(): HasMany
    {
        return $this->hasMany(ExtractionField::class);
    }

    /** @return HasMany<DocumentStateTransition, $this> */
    public function transitions(): HasMany
    {
        return $this->hasMany(DocumentStateTransition::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'state' => DocumentState::class,
            'size_bytes' => 'integer',
            'raw_ocr_text' => 'encrypted',
            'processing_attempts' => 'integer',
            'processing_dispatched_at' => 'immutable_datetime',
            'retain_original' => 'boolean',
            'processed_at' => 'immutable_datetime',
        ];
    }
}
