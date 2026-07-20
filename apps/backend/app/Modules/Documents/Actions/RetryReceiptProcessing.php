<?php

declare(strict_types=1);

namespace App\Modules\Documents\Actions;

use App\Modules\Documents\Enums\DocumentState;
use App\Modules\Documents\Jobs\ProcessReceipt;
use App\Modules\Documents\Models\ReceiptDocument;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

final readonly class RetryReceiptProcessing
{
    public function __construct(private TransitionDocumentState $transitionState) {}

    public function execute(ReceiptDocument $document, string $correlationId): ReceiptDocument
    {
        if (in_array($document->state, [DocumentState::ValidationPending, DocumentState::Processing], true)) {
            return $document;
        }
        if ($document->state !== DocumentState::Failed) {
            throw ValidationException::withMessages([
                'document' => ['Only a failed receipt can be retried. Quarantined files cannot be processed.'],
            ]);
        }

        $document->update(['last_error_code' => null, 'user_error' => null]);
        $this->transitionState->execute(
            $document,
            DocumentState::ValidationPending,
            'manual_retry_requested',
            $correlationId,
        );

        try {
            ProcessReceipt::dispatch($document->id, $correlationId)->onQueue('documents');
        } catch (Throwable $exception) {
            $document->update([
                'last_error_code' => 'queue_dispatch_unavailable',
                'user_error' => 'Receipt processing is delayed and will be retried safely.',
            ]);
            Log::warning('receipt.retry_dispatch_unavailable', [
                'document_id' => $document->id,
                'correlation_id' => $correlationId,
                'exception' => $exception::class,
            ]);
        }

        return $document;
    }
}
