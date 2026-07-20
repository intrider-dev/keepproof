<?php

declare(strict_types=1);

namespace App\Modules\Documents\Actions;

use App\Modules\Documents\Jobs\ProcessReceipt;
use App\Modules\Documents\Models\ReceiptDocument;
use Illuminate\Contracts\Bus\Dispatcher;
use Throwable;

final readonly class DispatchReceiptProcessing
{
    public function __construct(private Dispatcher $dispatcher) {}

    public function execute(ReceiptDocument $document, string $correlationId): void
    {
        $document->forceFill(['processing_dispatched_at' => now()])->save();

        try {
            $this->dispatcher->dispatch(
                (new ProcessReceipt($document->id, $correlationId))->onQueue('documents'),
            );
        } catch (Throwable $exception) {
            $document->forceFill(['processing_dispatched_at' => null])->save();

            throw $exception;
        }

        if ($document->last_error_code === 'queue_dispatch_unavailable') {
            $document->forceFill(['last_error_code' => null, 'user_error' => null])->save();
        }
    }
}
