<?php

declare(strict_types=1);

namespace App\Modules\Documents\Actions;

use App\Modules\Accounts\Models\User;
use App\Modules\Documents\Enums\DocumentState;
use App\Modules\Documents\Jobs\ProcessReceipt;
use App\Modules\Documents\Models\ReceiptDocument;
use App\Modules\Households\Models\Household;
use App\Modules\Purchases\Enums\PurchaseState;
use App\Modules\Purchases\Models\Purchase;
use App\Modules\Shared\Audit\AuditRecorder;
use App\Modules\Shared\Idempotency\IdempotencyRecord;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final readonly class UploadReceipt
{
    private const OPERATION = 'receipt.upload';

    public function __construct(
        private SanitizeReceiptUpload $sanitizer,
        private TransitionDocumentState $transitionState,
        private AuditRecorder $audit,
    ) {}

    public function execute(
        User $actor,
        Household $household,
        ?Purchase $purchase,
        UploadedFile $file,
        string $idempotencyKey,
        string $correlationId,
    ): ReceiptDocument {
        $keyHash = hash('sha256', $idempotencyKey);
        $existing = IdempotencyRecord::query()
            ->where('user_id', $actor->id)
            ->where('operation', self::OPERATION)
            ->where('key_hash', $keyHash)
            ->where('expires_at', '>', now())
            ->first();
        if ($existing !== null) {
            return ReceiptDocument::query()
                ->whereKey($existing->resource_id)
                ->where('household_id', $household->id)
                ->with('fields')
                ->firstOrFail();
        }

        if ($purchase !== null && $purchase->household_id !== $household->id) {
            throw new \LogicException('A receipt purchase must belong to the selected household.');
        }

        $receipt = $this->sanitizer->execute($file);
        $documentId = (string) Str::ulid();
        $disk = $this->disk();
        $storageKey = sprintf(
            'households/%s/receipts/%s/%s.%s',
            $household->id,
            $documentId,
            bin2hex(random_bytes(16)),
            $receipt->extension,
        );

        $stored = Storage::disk($disk)->put($storageKey, $receipt->binary, [
            'visibility' => 'private',
            'ContentType' => $receipt->mimeType,
        ]);
        if (! $stored) {
            throw new RuntimeException('Private receipt storage is unavailable.');
        }

        try {
            $document = DB::transaction(function () use (
                $actor,
                $household,
                $purchase,
                $receipt,
                $documentId,
                $disk,
                $storageKey,
                $correlationId,
                $keyHash,
            ): ReceiptDocument {
                $targetPurchase = $purchase ?? Purchase::query()->create([
                    'household_id' => $household->id,
                    'created_by_user_id' => $actor->id,
                    'state' => PurchaseState::NeedsReview,
                    'merchant' => 'Receipt pending review',
                    'purchase_date' => now($actor->timezone)->format('Y-m-d'),
                    'timezone' => $actor->timezone,
                    'receipt_number' => null,
                    'currency' => 'USD',
                    'total_minor' => 0,
                    'country_region' => 'Unspecified',
                ]);

                $created = ReceiptDocument::query()->create([
                    'id' => $documentId,
                    'purchase_id' => $targetPurchase->id,
                    'household_id' => $household->id,
                    'uploaded_by_user_id' => $actor->id,
                    'state' => DocumentState::Uploaded,
                    'storage_disk' => $disk,
                    'storage_key' => $storageKey,
                    'original_filename' => $receipt->displayFilename,
                    'mime_type' => $receipt->mimeType,
                    'size_bytes' => $receipt->sizeBytes,
                    'sha256' => $receipt->sha256,
                    'processing_attempts' => 0,
                    'correlation_id' => $correlationId,
                    'retain_original' => true,
                ]);
                $this->transitionState->recordInitial($created, 'receipt_uploaded', $correlationId, [
                    'mime_type' => $receipt->mimeType,
                    'size_bytes' => $receipt->sizeBytes,
                ]);
                $this->transitionState->execute(
                    $created,
                    DocumentState::ValidationPending,
                    'validation_queued',
                    $correlationId,
                );
                IdempotencyRecord::query()->create([
                    'user_id' => $actor->id,
                    'operation' => self::OPERATION,
                    'key_hash' => $keyHash,
                    'resource_type' => ReceiptDocument::class,
                    'resource_id' => $created->id,
                    'expires_at' => now()->addDay(),
                ]);
                $this->audit->record('receipt.uploaded', $actor, $household, $created, [
                    'mime_type' => $receipt->mimeType,
                    'size_bytes' => $receipt->sizeBytes,
                ]);

                return $created;
            });
        } catch (Throwable $exception) {
            Storage::disk($disk)->delete($storageKey);

            throw $exception;
        }

        try {
            ProcessReceipt::dispatch($document->id, $correlationId)->onQueue('documents');
        } catch (Throwable $exception) {
            $document->update([
                'last_error_code' => 'queue_dispatch_unavailable',
                'user_error' => 'Receipt processing is delayed and will be retried safely.',
            ]);
            Log::warning('receipt.dispatch_unavailable', [
                'document_id' => $document->id,
                'correlation_id' => $correlationId,
                'exception' => $exception::class,
            ]);
        }

        return $document->load('fields');
    }

    private function disk(): string
    {
        $disk = config('receipt_processing.disk');

        return is_string($disk) && $disk !== '' ? $disk : 'local';
    }
}
