<?php

declare(strict_types=1);

namespace App\Modules\Documents\Jobs;

use App\Modules\Documents\Actions\TransitionDocumentState;
use App\Modules\Documents\Actions\ValidateReceiptContent;
use App\Modules\Documents\Contracts\DocumentTextExtractor;
use App\Modules\Documents\Contracts\ReceiptDataExtractor;
use App\Modules\Documents\Enums\DocumentState;
use App\Modules\Documents\Exceptions\PermanentDocumentFailure;
use App\Modules\Documents\Exceptions\TransientDocumentFailure;
use App\Modules\Documents\Models\ReceiptDocument;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

final class ProcessReceipt implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $maxExceptions = 3;

    public int $timeout = 150;

    public bool $failOnTimeout = true;

    public function __construct(
        public readonly string $documentId,
        public readonly string $correlationId,
    ) {}

    /** @return list<int> */
    public function backoff(): array
    {
        return [15, 60, 180];
    }

    /** @return list<WithoutOverlapping> */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('receipt:'.$this->documentId))
                ->releaseAfter(15)
                ->expireAfter(180),
        ];
    }

    public function handle(
        TransitionDocumentState $transitionState,
        ValidateReceiptContent $validateContent,
        DocumentTextExtractor $textExtractor,
        ReceiptDataExtractor $dataExtractor,
    ): void {
        $document = ReceiptDocument::query()->find($this->documentId);
        if ($document === null || in_array($document->state, [
            DocumentState::NeedsReview,
            DocumentState::Ready,
            DocumentState::Quarantined,
        ], true)) {
            return;
        }

        try {
            if ($document->state === DocumentState::Uploaded) {
                $transitionState->execute(
                    $document,
                    DocumentState::ValidationPending,
                    'processing_recovered_upload',
                    $this->correlationId,
                );
            }
            if ($document->state === DocumentState::Failed) {
                $transitionState->execute(
                    $document,
                    DocumentState::ValidationPending,
                    'transient_retry_started',
                    $this->correlationId,
                    ['attempt' => $this->attempts()],
                );
            }
            if ($document->state === DocumentState::ValidationPending) {
                $transitionState->execute(
                    $document,
                    DocumentState::Processing,
                    'content_validation_started',
                    $this->correlationId,
                    ['attempt' => $this->attempts()],
                );
            }
            if ($document->state !== DocumentState::Processing) {
                return;
            }

            $binary = $this->readStoredReceipt($document);
            $validateContent->execute($binary, $document->mime_type, $document->sha256);
            $document->increment('processing_attempts');
            $text = $textExtractor->extract($binary, $document->mime_type);
            $extraction = $dataExtractor->extract($text);

            DB::transaction(function () use ($document, $text, $extraction, $transitionState): void {
                $locked = ReceiptDocument::query()->lockForUpdate()->findOrFail($document->id);
                if ($locked->state !== DocumentState::Processing) {
                    return;
                }

                $locked->update([
                    'raw_ocr_text' => $text->text,
                    'ocr_provider' => $text->provider,
                    'extraction_provider' => $extraction->provider,
                    'prompt_version' => $extraction->promptVersion,
                    'last_error_code' => null,
                    'user_error' => null,
                    'processed_at' => now(),
                ]);
                $locked->fields()->delete();
                foreach ($extraction->fields as $field) {
                    $locked->fields()->create($field->toPersistence());
                }

                $transitionState->execute(
                    $locked,
                    DocumentState::NeedsReview,
                    'extraction_requires_human_review',
                    $this->correlationId,
                    ['field_count' => count($extraction->fields)],
                );
            });
        } catch (PermanentDocumentFailure $exception) {
            $this->quarantine($document, $transitionState, $exception);
        } catch (TransientDocumentFailure $exception) {
            $this->recordTransientFailure($document, $transitionState, $exception);

            throw $exception;
        }
    }

    public function failed(?Throwable $exception): void
    {
        $document = ReceiptDocument::query()->find($this->documentId);
        if ($document === null) {
            return;
        }

        $document->update([
            'last_error_code' => 'processing_exhausted',
            'user_error' => 'Receipt processing failed after three safe attempts. You can retry or enter details manually.',
        ]);
        if (in_array($document->state, [DocumentState::ValidationPending, DocumentState::Processing], true)) {
            app(TransitionDocumentState::class)->execute(
                $document,
                DocumentState::Failed,
                'processing_attempts_exhausted',
                $this->correlationId,
            );
        }
        Log::error('receipt.processing_exhausted', [
            'document_id' => $this->documentId,
            'correlation_id' => $this->correlationId,
            'exception' => $exception === null ? null : $exception::class,
        ]);
    }

    private function readStoredReceipt(ReceiptDocument $document): string
    {
        try {
            $binary = Storage::disk($document->storage_disk)->get($document->storage_key);
        } catch (Throwable) {
            throw new TransientDocumentFailure('storage_read_unavailable');
        }

        if ($binary === null || $binary === '') {
            throw new TransientDocumentFailure('storage_object_empty');
        }

        return $binary;
    }

    private function quarantine(
        ReceiptDocument $document,
        TransitionDocumentState $transitionState,
        PermanentDocumentFailure $exception,
    ): void {
        $document->refresh();
        $document->update([
            'last_error_code' => $exception->failureCode,
            'user_error' => $exception->userMessage,
        ]);
        if (in_array($document->state, [DocumentState::ValidationPending, DocumentState::Processing], true)) {
            $transitionState->execute(
                $document,
                DocumentState::Quarantined,
                $exception->failureCode,
                $this->correlationId,
            );
        }
    }

    private function recordTransientFailure(
        ReceiptDocument $document,
        TransitionDocumentState $transitionState,
        TransientDocumentFailure $exception,
    ): void {
        $document->refresh();
        $document->update([
            'last_error_code' => $exception->failureCode,
            'user_error' => 'Receipt processing is temporarily unavailable and will retry automatically.',
        ]);
        if (in_array($document->state, [DocumentState::ValidationPending, DocumentState::Processing], true)) {
            $transitionState->execute(
                $document,
                DocumentState::Failed,
                $exception->failureCode,
                $this->correlationId,
                ['attempt' => $this->attempts()],
            );
        }
        Log::warning('receipt.processing_retry', [
            'document_id' => $this->documentId,
            'correlation_id' => $this->correlationId,
            'failure_code' => $exception->failureCode,
            'attempt' => $this->attempts(),
        ]);
    }
}
