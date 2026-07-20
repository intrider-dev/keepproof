<?php

declare(strict_types=1);

namespace App\Modules\Purchases\Actions;

use App\Modules\Accounts\Models\User;
use App\Modules\Purchases\Data\PurchaseData;
use App\Modules\Purchases\Models\Purchase;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;

final readonly class UpdatePurchase
{
    public function __construct(
        private CreatePurchase $createPurchase,
        private CalculatePurchaseDeadlines $calculateDeadlines,
        private AuditRecorder $audit,
    ) {}

    public function execute(User $actor, Purchase $purchase, PurchaseData $data): Purchase
    {
        return DB::transaction(function () use ($actor, $purchase, $data): Purchase {
            $purchase->update($this->createPurchase->attributes($data, $data->timezone ?? $purchase->timezone));
            $purchase->items()->delete();

            foreach ($data->items as $item) {
                $purchase->items()->create($item->toArray());
            }

            $this->calculateDeadlines->execute($purchase->refresh());
            $this->audit->record('purchase.updated', $actor, $purchase->household, $purchase, [
                'deadlines_recalculated' => true,
            ]);

            return $purchase->refresh()->load(['items', 'deadlines']);
        });
    }
}
