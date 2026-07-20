<?php

declare(strict_types=1);

namespace App\Modules\Documents\Actions;

use App\Modules\Accounts\Models\User;
use App\Modules\Documents\Enums\DocumentState;
use App\Modules\Documents\Models\ReceiptDocument;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final readonly class DeleteReceiptOriginal
{
    public function __construct(private AuditRecorder $audit) {}

    public function execute(User $actor, ReceiptDocument $document): ReceiptDocument
    {
        if (in_array($document->state, [
            DocumentState::Uploaded,
            DocumentState::ValidationPending,
            DocumentState::Processing,
        ], true)) {
            throw ValidationException::withMessages([
                'document' => ['Wait for processing to finish before deleting the original receipt.'],
            ]);
        }
        if (! $document->retain_original) {
            return $document;
        }

        $disk = Storage::disk($document->storage_disk);
        if ($disk->exists($document->storage_key) && ! $disk->delete($document->storage_key)) {
            throw new RuntimeException('The private receipt could not be deleted from storage.');
        }

        $document->update(['retain_original' => false]);
        $this->audit->record('receipt.original_deleted', $actor, $document->household, $document);

        return $document;
    }
}
