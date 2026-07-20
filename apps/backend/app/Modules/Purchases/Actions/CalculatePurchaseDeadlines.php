<?php

declare(strict_types=1);

namespace App\Modules\Purchases\Actions;

use App\Modules\Notifications\Actions\SchedulePurchaseReminders;
use App\Modules\Purchases\Models\Purchase;
use App\Modules\Purchases\Models\PurchaseDeadline;
use DateTimeImmutable;
use KeepProof\DeadlineEngine\DeadlineCalculator;
use KeepProof\DeadlineEngine\DeadlineKind;
use KeepProof\DeadlineEngine\Value\DeadlineResult;
use KeepProof\DeadlineEngine\Value\DeadlineTerms;
use KeepProof\DeadlineEngine\Value\LocalDateTime;
use KeepProof\DeadlineEngine\Value\ManualOverride;
use KeepProof\DeadlineEngine\Value\Period;
use KeepProof\DeadlineEngine\Value\PurchaseDeadlineRequest;

final readonly class CalculatePurchaseDeadlines
{
    public function __construct(
        private DeadlineCalculator $calculator,
        private SchedulePurchaseReminders $scheduleReminders,
    ) {}

    public function execute(Purchase $purchase): void
    {
        $returnTerms = $this->returnTerms($purchase);
        $warrantyTerms = $this->warrantyTerms($purchase);

        if ($returnTerms === null && $warrantyTerms === null) {
            $purchase->deadlines()->delete();

            return;
        }

        $purchasedAt = LocalDateTime::fromLocal(
            $purchase->purchase_date->format('Y-m-d').' 23:59:59',
            $purchase->timezone,
        );
        $result = $this->calculator->calculate(new PurchaseDeadlineRequest(
            purchasedAt: $purchasedAt,
            returnTerms: $returnTerms,
            warrantyTerms: $warrantyTerms,
        ));

        $this->persist($purchase, DeadlineKind::Return, $result->returnDeadline);
        $this->persist($purchase, DeadlineKind::Warranty, $result->warrantyDeadline);
        $this->scheduleReminders->execute($purchase->refresh());
    }

    private function returnTerms(Purchase $purchase): ?DeadlineTerms
    {
        if ($purchase->return_period_quantity === null || $purchase->return_period_unit === null) {
            return null;
        }

        return new DeadlineTerms(
            period: new Period($purchase->return_period_quantity, $purchase->return_period_unit),
            countingConvention: $purchase->return_counting_convention,
            manualOverride: $this->manualOverride(
                $purchase->manual_return_deadline_at,
                $purchase->manual_return_reason,
                $purchase->timezone,
            ),
        );
    }

    private function warrantyTerms(Purchase $purchase): ?DeadlineTerms
    {
        if ($purchase->warranty_period_quantity === null || $purchase->warranty_period_unit === null) {
            return null;
        }

        return new DeadlineTerms(
            period: new Period($purchase->warranty_period_quantity, $purchase->warranty_period_unit),
            countingConvention: $purchase->warranty_counting_convention,
            manualOverride: $this->manualOverride(
                $purchase->manual_warranty_deadline_at,
                $purchase->manual_warranty_reason,
                $purchase->timezone,
            ),
        );
    }

    private function manualOverride(?DateTimeImmutable $deadline, ?string $reason, string $timezone): ?ManualOverride
    {
        if ($deadline === null || $reason === null) {
            return null;
        }

        return new ManualOverride(LocalDateTime::fromDateTime($deadline)->inTimezone($timezone), $reason);
    }

    private function persist(Purchase $purchase, DeadlineKind $kind, ?DeadlineResult $result): void
    {
        if ($result === null) {
            $purchase->deadlines()->where('kind', $kind->value)->delete();

            return;
        }

        PurchaseDeadline::query()->updateOrCreate(
            ['purchase_id' => $purchase->id, 'kind' => $kind->value],
            [
                'household_id' => $purchase->household_id,
                'due_at' => $result->effectiveDeadline->dateTime(),
                'local_timezone' => $purchase->timezone,
                'source' => $result->source->value,
                'rule_summary' => $result->ruleSummary,
                'trace' => array_map(
                    static fn ($step): array => $step->jsonSerialize(),
                    $result->trace,
                ),
                'reminder_intervals' => $kind === DeadlineKind::Return ? [7, 3, 1] : [30, 7],
                'active' => true,
            ],
        );
    }
}
