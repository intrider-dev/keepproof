<?php

declare(strict_types=1);

namespace App\Modules\Purchases\Actions;

use App\Modules\Accounts\Models\User;
use App\Modules\Households\Models\Household;
use App\Modules\Purchases\Data\PurchaseData;
use App\Modules\Purchases\Enums\PurchaseState;
use App\Modules\Purchases\Models\Purchase;
use App\Modules\Shared\Audit\AuditRecorder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final readonly class CreatePurchase
{
    public function __construct(
        private CalculatePurchaseDeadlines $calculateDeadlines,
        private AuditRecorder $audit,
    ) {}

    public function execute(User $actor, Household $household, PurchaseData $data): Purchase
    {
        return DB::transaction(function () use ($actor, $household, $data): Purchase {
            $timezone = $data->timezone ?? $actor->timezone;
            $purchase = Purchase::query()->create([
                'household_id' => $household->id,
                'created_by_user_id' => $actor->id,
                'state' => PurchaseState::Active,
                ...$this->attributes($data, $timezone),
                'activated_at' => now(),
            ]);

            foreach ($data->items as $item) {
                $purchase->items()->create($item->toArray());
            }

            $this->calculateDeadlines->execute($purchase);
            $this->audit->record('purchase.created', $actor, $household, $purchase, [
                'source' => 'manual',
            ]);

            return $purchase->load(['items', 'deadlines']);
        });
    }

    /** @return array<string, mixed> */
    public function attributes(PurchaseData $data, string $timezone): array
    {
        return [
            'merchant' => trim($data->merchant),
            'purchase_date' => $data->purchaseDate,
            'timezone' => $timezone,
            'receipt_number' => $data->receiptNumber,
            'currency' => $data->currency,
            'total_minor' => $data->totalMinor,
            'country_region' => $data->countryRegion,
            'return_period_quantity' => $data->returnPeriodQuantity,
            'return_period_unit' => $data->returnPeriodUnit?->value,
            'return_counting_convention' => $data->returnCountingConvention->value,
            'warranty_period_quantity' => $data->warrantyPeriodQuantity,
            'warranty_period_unit' => $data->warrantyPeriodUnit?->value,
            'warranty_counting_convention' => $data->warrantyCountingConvention->value,
            'manual_return_deadline_at' => $this->deadline($data->manualReturnDeadline, $timezone),
            'manual_return_reason' => $data->manualReturnReason,
            'manual_warranty_deadline_at' => $this->deadline($data->manualWarrantyDeadline, $timezone),
            'manual_warranty_reason' => $data->manualWarrantyReason,
            'notes' => $data->notes,
        ];
    }

    private function deadline(?string $date, string $timezone): ?CarbonImmutable
    {
        return $date === null ? null : CarbonImmutable::createFromFormat('!Y-m-d H:i:s', $date.' 23:59:59', $timezone);
    }
}
