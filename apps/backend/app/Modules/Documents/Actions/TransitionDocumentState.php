<?php

declare(strict_types=1);

namespace App\Modules\Documents\Actions;

use App\Modules\Documents\Enums\DocumentState;
use App\Modules\Documents\Exceptions\InvalidDocumentTransition;
use App\Modules\Documents\Models\ReceiptDocument;
use Illuminate\Support\Facades\DB;

final class TransitionDocumentState
{
    /** @var array<string, list<DocumentState>> */
    private const TRANSITIONS = [
        DocumentState::Uploaded->value => [DocumentState::ValidationPending],
        DocumentState::ValidationPending->value => [
            DocumentState::Processing,
            DocumentState::Failed,
            DocumentState::Quarantined,
        ],
        DocumentState::Processing->value => [
            DocumentState::NeedsReview,
            DocumentState::Failed,
            DocumentState::Quarantined,
        ],
        DocumentState::NeedsReview->value => [DocumentState::Ready, DocumentState::Processing],
        DocumentState::Failed->value => [DocumentState::ValidationPending],
        DocumentState::Ready->value => [DocumentState::NeedsReview],
        DocumentState::Quarantined->value => [],
    ];

    /** @param array<string, bool|int|string|null> $metadata */
    public function execute(
        ReceiptDocument $document,
        DocumentState $to,
        string $reason,
        string $correlationId,
        array $metadata = [],
    ): ReceiptDocument {
        return DB::transaction(function () use ($document, $to, $reason, $correlationId, $metadata): ReceiptDocument {
            $locked = ReceiptDocument::query()->lockForUpdate()->findOrFail($document->id);
            $from = $locked->state;

            if (! in_array($to, self::TRANSITIONS[$from->value], true)) {
                throw new InvalidDocumentTransition(sprintf(
                    'Document state cannot move from %s to %s.',
                    $from->value,
                    $to->value,
                ));
            }

            $locked->update(['state' => $to]);
            $locked->transitions()->create([
                'from_state' => $from,
                'to_state' => $to,
                'reason' => $reason,
                'correlation_id' => $correlationId,
                'metadata' => $metadata,
                'occurred_at' => now(),
            ]);

            $document->setRawAttributes($locked->getAttributes(), true);

            return $document;
        });
    }

    /** @param array<string, bool|int|string|null> $metadata */
    public function recordInitial(
        ReceiptDocument $document,
        string $reason,
        string $correlationId,
        array $metadata = [],
    ): void {
        $document->transitions()->create([
            'from_state' => null,
            'to_state' => $document->state,
            'reason' => $reason,
            'correlation_id' => $correlationId,
            'metadata' => $metadata,
            'occurred_at' => now(),
        ]);
    }
}
