<?php

declare(strict_types=1);

namespace App\Modules\Documents\Models;

use App\Modules\Documents\Enums\DocumentState;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $receipt_document_id
 * @property DocumentState|null $from_state
 * @property DocumentState $to_state
 * @property string $reason
 * @property string $correlation_id
 * @property array<string, bool|int|string|null> $metadata
 * @property CarbonImmutable $occurred_at
 * @property-read ReceiptDocument $document
 */
final class DocumentStateTransition extends Model
{
    use HasUlids;

    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = [
        'from_state',
        'to_state',
        'reason',
        'correlation_id',
        'metadata',
        'occurred_at',
    ];

    /** @return BelongsTo<ReceiptDocument, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(ReceiptDocument::class, 'receipt_document_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'from_state' => DocumentState::class,
            'to_state' => DocumentState::class,
            'metadata' => 'array',
            'occurred_at' => 'immutable_datetime',
        ];
    }
}
