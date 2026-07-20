<?php

declare(strict_types=1);

namespace App\Modules\Documents\Actions;

use App\Modules\Accounts\Models\User;
use App\Modules\Documents\Enums\DocumentState;
use App\Modules\Documents\Models\ReceiptDocument;
use App\Modules\Purchases\Actions\UpdatePurchase;
use App\Modules\Purchases\Data\PurchaseData;
use App\Modules\Purchases\Enums\PurchaseState;
use App\Modules\Shared\Audit\AuditRecorder;
use App\Modules\Shared\Idempotency\IdempotencyRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class ConfirmExtraction
{
    private const OPERATION = 'receipt.confirm';

    public function __construct(
        private UpdatePurchase $updatePurchase,
        private TransitionDocumentState $transitionState,
        private AuditRecorder $audit,
    ) {}

    public function execute(
        User $actor,
        ReceiptDocument $document,
        PurchaseData $data,
        string $idempotencyKey,
        string $correlationId,
    ): ReceiptDocument {
        if ($document->state === DocumentState::Ready) {
            return $document->load(['fields', 'purchase.items', 'purchase.deadlines']);
        }
        if ($document->state !== DocumentState::NeedsReview) {
            throw ValidationException::withMessages([
                'document' => ['Only a receipt awaiting review can be confirmed.'],
            ]);
        }

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
                ->where('household_id', $document->household_id)
                ->with(['fields', 'purchase.items', 'purchase.deadlines'])
                ->firstOrFail();
        }

        return DB::transaction(function () use (
            $actor,
            $document,
            $data,
            $idempotencyKey,
            $keyHash,
            $correlationId,
        ): ReceiptDocument {
            $purchase = $this->updatePurchase->execute($actor, $document->purchase, $data);
            $purchase->update([
                'state' => PurchaseState::Active,
                'activated_at' => now(),
            ]);

            $confirmed = $this->confirmedValues($data);
            foreach ($document->fields as $field) {
                $field->update(['confirmed_value' => $confirmed[$field->field_key] ?? null]);
            }

            $this->transitionState->execute(
                $document,
                DocumentState::Ready,
                'human_confirmation_completed',
                $correlationId,
            );
            IdempotencyRecord::query()->create([
                'user_id' => $actor->id,
                'operation' => self::OPERATION,
                'key_hash' => $keyHash,
                'resource_type' => ReceiptDocument::class,
                'resource_id' => $document->id,
                'expires_at' => now()->addDay(),
            ]);
            $this->audit->record('receipt.confirmed', $actor, $document->household, $document, [
                'idempotency_key_hash_prefix' => substr(hash('sha256', $idempotencyKey), 0, 12),
            ]);

            return $document->refresh()->load(['fields', 'purchase.items', 'purchase.deadlines']);
        });
    }

    /** @return array<string, string> */
    private function confirmedValues(PurchaseData $data): array
    {
        $values = [
            'merchant' => $data->merchant,
            'purchase_date' => $data->purchaseDate,
            'currency' => $data->currency,
            'total_minor' => (string) $data->totalMinor,
            'country_region' => $data->countryRegion,
            'return_counting_convention' => $data->returnCountingConvention->value,
            'warranty_counting_convention' => $data->warrantyCountingConvention->value,
            'item_name' => $data->items[0]->name,
        ];
        if ($data->receiptNumber !== null) {
            $values['receipt_number'] = $data->receiptNumber;
        }
        if ($data->returnPeriodQuantity !== null) {
            $values['return_period_quantity'] = (string) $data->returnPeriodQuantity;
        }
        if ($data->returnPeriodUnit !== null) {
            $values['return_period_unit'] = $data->returnPeriodUnit->value;
        }
        if ($data->warrantyPeriodQuantity !== null) {
            $values['warranty_period_quantity'] = (string) $data->warrantyPeriodQuantity;
        }
        if ($data->warrantyPeriodUnit !== null) {
            $values['warranty_period_unit'] = $data->warrantyPeriodUnit->value;
        }

        return $values;
    }
}
