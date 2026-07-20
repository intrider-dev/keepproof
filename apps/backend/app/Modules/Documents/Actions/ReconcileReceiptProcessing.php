<?php

declare(strict_types=1);

namespace App\Modules\Documents\Actions;

use App\Modules\Documents\Enums\DocumentState;
use App\Modules\Documents\Models\ReceiptDocument;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

final readonly class ReconcileReceiptProcessing
{
    public function __construct(private DispatchReceiptProcessing $dispatchProcessing) {}

    /** @return array{dispatched: int, failed: int} */
    public function execute(): array
    {
        $cutoff = now()->subSeconds($this->staleAfterSeconds());
        $candidates = ReceiptDocument::query()
            ->whereIn('state', [DocumentState::ValidationPending, DocumentState::Processing])
            ->where('retain_original', true)
            ->where(static function ($query) use ($cutoff): void {
                $query->whereNull('processing_dispatched_at')
                    ->orWhere('processing_dispatched_at', '<=', $cutoff);
            })
            ->orderBy('created_at')
            ->limit($this->batchSize())
            ->get(['id']);

        $dispatched = 0;
        $failed = 0;

        foreach ($candidates as $candidateDocument) {
            $document = DB::transaction(function () use ($candidateDocument, $cutoff): ?ReceiptDocument {
                $candidate = ReceiptDocument::query()
                    ->whereKey($candidateDocument->id)
                    ->lockForUpdate()
                    ->first();
                if ($candidate === null || ! $this->isDispatchable($candidate, $cutoff)) {
                    return null;
                }

                // This timestamp is a renewable database lease. A process crash before queue dispatch
                // becomes recoverable after the configured stale interval.
                $candidate->forceFill(['processing_dispatched_at' => now()])->save();

                return $candidate;
            });

            if ($document === null) {
                continue;
            }

            try {
                $this->dispatchProcessing->execute($document, $document->correlation_id);
                $dispatched++;
            } catch (Throwable $exception) {
                $document->forceFill([
                    'processing_dispatched_at' => null,
                    'last_error_code' => 'queue_dispatch_unavailable',
                    'user_error' => 'Receipt processing is delayed and will be retried safely.',
                ])->save();
                $failed++;
                Log::warning('receipt.reconciliation_dispatch_unavailable', [
                    'document_id' => $document->id,
                    'correlation_id' => $document->correlation_id,
                    'exception' => $exception::class,
                ]);
            }
        }

        return ['dispatched' => $dispatched, 'failed' => $failed];
    }

    private function isDispatchable(ReceiptDocument $document, \DateTimeInterface $cutoff): bool
    {
        if (! $document->retain_original || ! in_array($document->state, [
            DocumentState::ValidationPending,
            DocumentState::Processing,
        ], true)) {
            return false;
        }

        return $document->processing_dispatched_at === null
            || $document->processing_dispatched_at->lessThanOrEqualTo($cutoff);
    }

    private function staleAfterSeconds(): int
    {
        $seconds = config('receipt_processing.reconcile_after_seconds', 300);

        return is_int($seconds) ? min(max($seconds, 30), 3600) : 300;
    }

    private function batchSize(): int
    {
        $size = config('receipt_processing.reconcile_batch_size', 100);

        return is_int($size) ? min(max($size, 1), 500) : 100;
    }
}
