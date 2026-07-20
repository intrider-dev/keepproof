<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Accounts\Actions\DeleteAccount;
use App\Modules\Accounts\Actions\RegisterAccount;
use App\Modules\Accounts\Models\User;
use App\Modules\Documents\Actions\TransitionDocumentState;
use App\Modules\Documents\Enums\DocumentState;
use App\Modules\Documents\Models\ReceiptDocument;
use App\Modules\Households\Enums\HouseholdRole;
use App\Modules\Households\Models\Household;
use App\Modules\Households\Models\HouseholdMember;
use App\Modules\Notifications\Enums\ReminderChannel;
use App\Modules\Notifications\Models\NotificationPreference;
use App\Modules\Notifications\Notifications\DeadlineReminderNotification;
use App\Modules\Purchases\Actions\CreatePurchase;
use App\Modules\Purchases\Data\PurchaseData;
use App\Modules\Purchases\Data\PurchaseItemData;
use App\Modules\Purchases\Enums\PurchaseState;
use App\Modules\Purchases\Models\Purchase;
use App\Modules\Purchases\Models\PurchaseDeadline;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use KeepProof\DeadlineEngine\CountingConvention;
use KeepProof\DeadlineEngine\DeadlineKind;
use KeepProof\DeadlineEngine\PeriodUnit;
use RuntimeException;

final class DemoDatasetSeeder extends Seeder
{
    public const EMAIL = 'demo@keepproof.local';

    public const PASSWORD = 'KeepProof!2026';

    private const TIMEZONE = 'America/New_York';

    public function run(
        RegisterAccount $registerAccount,
        DeleteAccount $deleteAccount,
        CreatePurchase $createPurchase,
        TransitionDocumentState $transitionState,
    ): void {
        if (! app()->environment('local', 'testing')) {
            throw new RuntimeException('The synthetic demo dataset may only be seeded locally or in tests.');
        }

        $existing = User::query()->where('email', self::EMAIL)->first();
        if ($existing !== null) {
            // The production delete flow must reject shared owned households. This local-only
            // reset first removes the known synthetic guest membership so the same invariant
            // remains enforced for every user-triggered deletion.
            HouseholdMember::query()
                ->whereIn('household_id', Household::query()->where('owner_user_id', $existing->id)->select('id'))
                ->where('user_id', '!=', $existing->id)
                ->delete();
            $deleteAccount->execute($existing);
        }

        $demo = $registerAccount->execute(
            name: 'Alex Morgan',
            email: self::EMAIL,
            password: self::PASSWORD,
            timezone: self::TIMEZONE,
            locale: 'en',
        );
        $demo->forceFill(['email_verified_at' => now()])->save();
        $household = $this->demoHousehold($demo);
        $household->update(['name' => 'Morgan household']);
        $this->addHouseholdMember($household);

        NotificationPreference::query()->create([
            'user_id' => $demo->id,
            'in_app_enabled' => true,
            'email_enabled' => true,
            'return_intervals' => [7, 3, 1],
            'warranty_intervals' => [30, 7],
            'quiet_hours_start' => '22:00',
            'quiet_hours_end' => '07:00',
        ]);

        $today = CarbonImmutable::now(self::TIMEZONE)->startOfDay();
        $urgent = $createPurchase->execute(
            $demo,
            $household,
            $this->purchaseData(
                merchant: 'North & Pine',
                purchaseDate: $today->subDays(26)->format('Y-m-d'),
                receiptNumber: 'NP-48291',
                totalMinor: 8900,
                returnDays: 30,
                warrantyMonths: 24,
                notes: 'Keep the kettle packaging until the return window closes.',
                items: [new PurchaseItemData('Pour-over electric kettle', 1, 8900, 'NP-DEMO-48291')],
            ),
        );
        $createPurchase->execute(
            $demo,
            $household,
            $this->purchaseData(
                merchant: 'Auralink Studio',
                purchaseDate: $today->subDays(12)->format('Y-m-d'),
                receiptNumber: 'AS-71824',
                totalMinor: 24999,
                returnDays: 30,
                warrantyMonths: 24,
                notes: 'Holiday extension is printed on the order confirmation.',
                items: [new PurchaseItemData('QuietTone wireless headphones', 1, 24999, null)],
                manualReturnDeadline: $today->addDays(24)->format('Y-m-d'),
                manualReturnReason: 'Merchant holiday return extension printed on the receipt.',
            ),
        );
        $proofPurchase = $createPurchase->execute(
            $demo,
            $household,
            $this->purchaseData(
                merchant: 'Northstar Electronics',
                purchaseDate: '2026-06-12',
                receiptNumber: 'KP-2026-0612',
                totalMinor: 12999,
                returnDays: 30,
                warrantyMonths: 24,
                notes: 'Synthetic purchase used to demonstrate portable claim packages.',
                items: [new PurchaseItemData('Trailview camera backpack', 1, 12999, null)],
            ),
        );

        $this->attachReceipt(
            purchase: $proofPurchase,
            uploader: $demo,
            fixture: 'northstar-receipt.png',
            finalState: DocumentState::Ready,
            fields: $this->northstarElectronicsFields(),
            rawText: $this->northstarElectronicsText(),
            transitionState: $transitionState,
        );

        $reviewPurchase = Purchase::query()->create([
            'household_id' => $household->id,
            'created_by_user_id' => $demo->id,
            'state' => PurchaseState::NeedsReview,
            'merchant' => 'Receipt pending review',
            'purchase_date' => '2026-07-08',
            'timezone' => self::TIMEZONE,
            'receipt_number' => null,
            'currency' => 'USD',
            'total_minor' => 13950,
            'country_region' => 'US',
        ]);
        $reviewPurchase->items()->create([
            'name' => 'Unconfirmed receipt item',
            'quantity' => 1,
            'unit_price_minor' => 13950,
            'serial_number' => null,
        ]);
        $this->attachReceipt(
            purchase: $reviewPurchase,
            uploader: $demo,
            fixture: 'northstar-home-receipt.png',
            finalState: DocumentState::NeedsReview,
            fields: $this->northstarHomeFields(),
            rawText: $this->northstarHomeText(),
            transitionState: $transitionState,
        );

        $returnDeadline = $urgent->deadlines()
            ->where('kind', DeadlineKind::Return)
            ->with('purchase')
            ->first();
        if (! $returnDeadline instanceof PurchaseDeadline) {
            throw new RuntimeException('The demo return deadline was not calculated.');
        }
        $demo->notify(new DeadlineReminderNotification($returnDeadline, ReminderChannel::InApp));
    }

    private function demoHousehold(User $demo): Household
    {
        $household = $demo->households()->first();
        if (! $household instanceof Household) {
            throw new RuntimeException('Demo registration did not create a personal household.');
        }

        return $household;
    }

    private function addHouseholdMember(Household $household): void
    {
        $member = User::query()->updateOrCreate(
            ['email' => 'jamie@keepproof.local'],
            [
                'name' => 'Jamie Morgan',
                'password' => Str::password(24),
                'timezone' => self::TIMEZONE,
                'locale' => 'en',
            ],
        );
        $member->forceFill(['email_verified_at' => now()])->save();
        HouseholdMember::query()->updateOrCreate(
            ['household_id' => $household->id, 'user_id' => $member->id],
            ['role' => HouseholdRole::Member, 'joined_at' => now()],
        );
    }

    /** @param non-empty-list<PurchaseItemData> $items */
    private function purchaseData(
        string $merchant,
        string $purchaseDate,
        string $receiptNumber,
        int $totalMinor,
        int $returnDays,
        int $warrantyMonths,
        string $notes,
        array $items,
        ?string $manualReturnDeadline = null,
        ?string $manualReturnReason = null,
    ): PurchaseData {
        return new PurchaseData(
            merchant: $merchant,
            purchaseDate: $purchaseDate,
            timezone: self::TIMEZONE,
            receiptNumber: $receiptNumber,
            currency: 'USD',
            totalMinor: $totalMinor,
            countryRegion: 'US',
            returnPeriodQuantity: $returnDays,
            returnPeriodUnit: PeriodUnit::Days,
            returnCountingConvention: CountingConvention::PurchaseDayExclusive,
            warrantyPeriodQuantity: $warrantyMonths,
            warrantyPeriodUnit: PeriodUnit::Months,
            warrantyCountingConvention: CountingConvention::PurchaseDayExclusive,
            manualReturnDeadline: $manualReturnDeadline,
            manualReturnReason: $manualReturnReason,
            manualWarrantyDeadline: null,
            manualWarrantyReason: null,
            notes: $notes,
            items: $items,
        );
    }

    /** @param list<array{key: string, raw: string, normalized: string, confidence: string, source: string}> $fields */
    private function attachReceipt(
        Purchase $purchase,
        User $uploader,
        string $fixture,
        DocumentState $finalState,
        array $fields,
        string $rawText,
        TransitionDocumentState $transitionState,
    ): ReceiptDocument {
        $path = base_path('../../tests/fixtures/receipts/generated/'.$fixture);
        $binary = file_get_contents($path);
        if (! is_string($binary)) {
            throw new RuntimeException('A synthetic receipt fixture could not be read: '.$fixture);
        }

        $disk = config('receipt_processing.disk');
        if (! is_string($disk) || $disk === '') {
            throw new RuntimeException('The receipt storage disk is not configured.');
        }
        $documentId = (string) Str::ulid();
        $storageKey = sprintf(
            'households/%s/receipts/%s/%s.png',
            $purchase->household_id,
            $documentId,
            bin2hex(random_bytes(12)),
        );
        if (! Storage::disk($disk)->put($storageKey, $binary, ['visibility' => 'private'])) {
            throw new RuntimeException('The synthetic receipt could not be written to private storage.');
        }

        $correlationId = (string) Str::uuid();
        $document = ReceiptDocument::query()->create([
            'id' => $documentId,
            'purchase_id' => $purchase->id,
            'household_id' => $purchase->household_id,
            'uploaded_by_user_id' => $uploader->id,
            'state' => DocumentState::Uploaded,
            'storage_disk' => $disk,
            'storage_key' => $storageKey,
            'original_filename' => $fixture,
            'mime_type' => 'image/png',
            'size_bytes' => strlen($binary),
            'sha256' => hash('sha256', $binary),
            'processing_attempts' => 1,
            'correlation_id' => $correlationId,
            'retain_original' => true,
        ]);
        $transitionState->recordInitial($document, 'demo_receipt_uploaded', $correlationId, [
            'synthetic_fixture' => true,
        ]);
        $transitionState->execute(
            $document,
            DocumentState::ValidationPending,
            'demo_validation_queued',
            $correlationId,
        );
        $transitionState->execute(
            $document,
            DocumentState::Processing,
            'demo_local_processing_started',
            $correlationId,
        );
        $document->update([
            'raw_ocr_text' => $rawText,
            'ocr_provider' => 'deterministic-fixture-v1',
            'extraction_provider' => 'deterministic-patterns',
            'prompt_version' => 'receipt-patterns-v1',
            'processed_at' => now(),
        ]);
        foreach ($fields as $field) {
            $document->fields()->create([
                'field_key' => $field['key'],
                'raw_value' => $field['raw'],
                'normalized_value' => $field['normalized'],
                'confidence' => $field['confidence'],
                'source' => $field['source'],
                'validation_errors' => [],
                'confirmed_value' => $finalState === DocumentState::Ready ? $field['normalized'] : null,
            ]);
        }
        $transitionState->execute(
            $document,
            DocumentState::NeedsReview,
            'demo_extraction_requires_review',
            $correlationId,
            ['field_count' => count($fields)],
        );
        if ($finalState === DocumentState::Ready) {
            $transitionState->execute(
                $document,
                DocumentState::Ready,
                'demo_extraction_confirmed',
                $correlationId,
            );
        }

        return $document->refresh();
    }

    /** @return list<array{key: string, raw: string, normalized: string, confidence: string, source: string}> */
    private function northstarElectronicsFields(): array
    {
        return [
            $this->field('merchant', 'Northstar Electronics', 'Northstar Electronics', '0.9900', 'ocr:header'),
            $this->field('purchase_date', '2026-06-12', '2026-06-12', '0.9900', 'ocr:purchase date'),
            $this->field('receipt_number', 'KP-2026-0612', 'KP-2026-0612', '0.9800', 'ocr:receipt'),
            $this->field('currency', 'USD', 'USD', '1.0000', 'ocr:total'),
            $this->field('country_region', 'US', 'US', '0.9500', 'derived:fixture locale'),
            $this->field('total_minor', '129.99', '12999', '0.9900', 'ocr:total'),
            $this->field('item_name', 'Trailview camera backpack', 'Trailview camera backpack', '0.9700', 'ocr:item'),
            $this->field('return_period_quantity', '30', '30', '0.9600', 'ocr:return policy'),
            $this->field('return_period_unit', 'days', 'days', '0.9600', 'derived:return policy'),
            $this->field('warranty_period_quantity', '24', '24', '0.9400', 'ocr:warranty policy'),
            $this->field('warranty_period_unit', 'months', 'months', '0.9400', 'derived:warranty policy'),
        ];
    }

    /** @return list<array{key: string, raw: string, normalized: string, confidence: string, source: string}> */
    private function northstarHomeFields(): array
    {
        return [
            $this->field('merchant', 'Northstar Home', 'Northstar Home', '0.9800', 'ocr:header'),
            $this->field('purchase_date', '2026-07-08', '2026-07-08', '0.9700', 'ocr:purchase date'),
            $this->field('receipt_number', 'NS-1042', 'NS-1042', '0.9300', 'ocr:receipt'),
            $this->field('currency', 'USD', 'USD', '0.9900', 'ocr:total'),
            $this->field('country_region', 'US', 'US', '0.7200', 'derived:fixture locale'),
            $this->field('total_minor', '139.50', '13950', '0.9500', 'ocr:total'),
            $this->field('item_name', 'Linen floor lamp', 'Linen floor lamp', '0.9100', 'ocr:item'),
            $this->field('return_period_quantity', '30', '30', '0.8600', 'ocr:return policy'),
            $this->field('return_period_unit', 'days', 'days', '0.8600', 'derived:return policy'),
            $this->field('warranty_period_quantity', '12', '12', '0.8400', 'ocr:warranty policy'),
            $this->field('warranty_period_unit', 'months', 'months', '0.8400', 'derived:warranty policy'),
        ];
    }

    /** @return array{key: string, raw: string, normalized: string, confidence: string, source: string} */
    private function field(
        string $key,
        string $raw,
        string $normalized,
        string $confidence,
        string $source,
    ): array {
        return compact('key', 'raw', 'normalized', 'confidence', 'source');
    }

    private function northstarElectronicsText(): string
    {
        return "MERCHANT: Northstar Electronics\nPURCHASE DATE: 2026-06-12\nRECEIPT: KP-2026-0612\nCURRENCY: USD\nTOTAL: 129.99\nITEM: Trailview camera backpack\nRETURN DAYS: 30\nWARRANTY MONTHS: 24";
    }

    private function northstarHomeText(): string
    {
        return "MERCHANT: Northstar Home\nPURCHASE DATE: 2026-07-08\nRECEIPT: NS-1042\nCURRENCY: USD\nTOTAL: 139.50\nITEM: Linen floor lamp\nRETURN DAYS: 30\nWARRANTY MONTHS: 12";
    }
}
