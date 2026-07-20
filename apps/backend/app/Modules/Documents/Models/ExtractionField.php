<?php

declare(strict_types=1);

namespace App\Modules\Documents\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $receipt_document_id
 * @property string $field_key
 * @property string|null $raw_value
 * @property string|null $normalized_value
 * @property string|null $confidence
 * @property string $source
 * @property list<string> $validation_errors
 * @property string|null $confirmed_value
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read ReceiptDocument $document
 */
final class ExtractionField extends Model
{
    use HasUlids;

    /** @var list<string> */
    protected $fillable = [
        'field_key',
        'raw_value',
        'normalized_value',
        'confidence',
        'source',
        'validation_errors',
        'confirmed_value',
    ];

    /** @return BelongsTo<ReceiptDocument, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(ReceiptDocument::class, 'receipt_document_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['validation_errors' => 'array'];
    }
}
