<?php

declare(strict_types=1);

namespace App\Modules\Documents\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Documents\Actions\ConfirmExtraction;
use App\Modules\Documents\Actions\DeleteReceiptOriginal;
use App\Modules\Documents\Actions\RetryReceiptProcessing;
use App\Modules\Documents\Actions\UploadReceipt;
use App\Modules\Documents\Enums\DocumentState;
use App\Modules\Documents\Http\Requests\ConfirmExtractionRequest;
use App\Modules\Documents\Http\Requests\UploadReceiptRequest;
use App\Modules\Documents\Http\Resources\ReceiptDocumentResource;
use App\Modules\Documents\Models\ReceiptDocument;
use App\Modules\Households\Models\Household;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ReceiptDocumentController extends Controller
{
    public function store(
        UploadReceiptRequest $request,
        Household $household,
        UploadReceipt $uploadReceipt,
    ): JsonResponse {
        $this->authorize('create', [ReceiptDocument::class, $household]);
        $purchaseId = $request->purchaseId();
        $purchase = $purchaseId === null
            ? null
            : $household->purchases()->whereKey($purchaseId)->firstOrFail();
        $document = $uploadReceipt->execute(
            $this->authenticatedUser($request),
            $household,
            $purchase,
            $request->receipt(),
            $request->externalAiConsentGranted(),
            $request->idempotencyKey(),
            $request->correlationId(),
        );

        return (new ReceiptDocumentResource($document))->response()->setStatusCode(202);
    }

    public function show(Household $household, ReceiptDocument $document): ReceiptDocumentResource
    {
        $this->authorize('view', $document);

        return new ReceiptDocumentResource($document->load('fields'));
    }

    public function download(
        Request $request,
        Household $household,
        ReceiptDocument $document,
        AuditRecorder $audit,
    ): JsonResponse {
        $this->authorize('view', $document);
        abort_if(
            $document->state === DocumentState::Quarantined || ! $document->retain_original,
            404,
        );
        $minutes = config('receipt_processing.signed_url_minutes', 5);
        $ttl = is_int($minutes) ? min(max($minutes, 1), 15) : 5;
        $expiresAt = now()->addMinutes($ttl);
        $url = URL::temporarySignedRoute(
            'receipt.original.download',
            $expiresAt,
            ['documentId' => $document->id],
            absolute: false,
        );
        $audit->record('receipt.download_url_created', $this->authenticatedUser($request), $household, $document, [
            'expires_in_seconds' => $ttl * 60,
        ]);

        return response()->json([
            'data' => [
                'url' => $url,
                'expires_at' => $expiresAt->toAtomString(),
            ],
        ])->header('Cache-Control', 'private, no-store');
    }

    public function serveOriginal(string $documentId): StreamedResponse
    {
        $document = ReceiptDocument::query()->findOrFail($documentId);
        $this->authorize('view', $document);
        abort_if(
            $document->state === DocumentState::Quarantined || ! $document->retain_original,
            404,
        );

        $storage = Storage::disk($document->storage_disk);
        abort_unless($storage->exists($document->storage_key), 404);

        return $storage->download(
            $document->storage_key,
            $this->downloadFilename($document),
            [
                'Content-Type' => $document->mime_type,
                'Cache-Control' => 'private, no-store, max-age=0',
                'Pragma' => 'no-cache',
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }

    public function confirm(
        ConfirmExtractionRequest $request,
        Household $household,
        ReceiptDocument $document,
        ConfirmExtraction $confirmExtraction,
    ): ReceiptDocumentResource {
        $this->authorize('update', $document);
        $confirmed = $confirmExtraction->execute(
            $this->authenticatedUser($request),
            $document,
            $request->purchaseData(),
            $request->idempotencyKey(),
            $request->correlationId(),
        );

        return new ReceiptDocumentResource($confirmed);
    }

    public function retry(
        Request $request,
        Household $household,
        ReceiptDocument $document,
        RetryReceiptProcessing $retryProcessing,
    ): JsonResponse {
        $this->authorize('update', $document);
        $correlationId = $request->attributes->get('correlation_id');
        if (! is_string($correlationId)) {
            throw new \LogicException('A correlation ID must be assigned before receipt retry.');
        }
        $retried = $retryProcessing->execute($document, $correlationId);

        return (new ReceiptDocumentResource($retried->load('fields')))->response()->setStatusCode(202);
    }

    public function destroyOriginal(
        Request $request,
        Household $household,
        ReceiptDocument $document,
        DeleteReceiptOriginal $deleteOriginal,
    ): ReceiptDocumentResource {
        $this->authorize('delete', $document);
        $updated = $deleteOriginal->execute($this->authenticatedUser($request), $document);

        return new ReceiptDocumentResource($updated->load('fields'));
    }

    private function downloadFilename(ReceiptDocument $document): string
    {
        return match ($document->mime_type) {
            'image/jpeg' => 'receipt.jpg',
            'image/png' => 'receipt.png',
            'application/pdf' => 'receipt.pdf',
            default => 'receipt',
        };
    }
}
