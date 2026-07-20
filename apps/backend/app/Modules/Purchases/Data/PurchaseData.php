<?php

declare(strict_types=1);

namespace App\Modules\Purchases\Data;

use KeepProof\DeadlineEngine\CountingConvention;
use KeepProof\DeadlineEngine\PeriodUnit;

final readonly class PurchaseData
{
    /** @param non-empty-list<PurchaseItemData> $items */
    public function __construct(
        public string $merchant,
        public string $purchaseDate,
        public ?string $timezone,
        public ?string $receiptNumber,
        public string $currency,
        public int $totalMinor,
        public string $countryRegion,
        public ?int $returnPeriodQuantity,
        public ?PeriodUnit $returnPeriodUnit,
        public CountingConvention $returnCountingConvention,
        public ?int $warrantyPeriodQuantity,
        public ?PeriodUnit $warrantyPeriodUnit,
        public CountingConvention $warrantyCountingConvention,
        public ?string $manualReturnDeadline,
        public ?string $manualReturnReason,
        public ?string $manualWarrantyDeadline,
        public ?string $manualWarrantyReason,
        public ?string $notes,
        public array $items,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromValidated(array $data): self
    {
        $items = $data['items'] ?? null;

        if (! is_array($items) || $items === []) {
            throw new \InvalidArgumentException('items must be a validated non-empty array.');
        }

        $itemData = [];
        foreach ($items as $item) {
            if (! is_array($item)) {
                throw new \InvalidArgumentException('Each item must be a validated object.');
            }

            /** @var array<string, mixed> $item */
            $itemData[] = PurchaseItemData::fromArray($item);
        }

        $returnUnit = self::nullableString($data, 'return_period_unit');
        $warrantyUnit = self::nullableString($data, 'warranty_period_unit');
        $returnConvention = self::nullableString($data, 'return_counting_convention')
            ?? CountingConvention::PurchaseDayExclusive->value;
        $warrantyConvention = self::nullableString($data, 'warranty_counting_convention')
            ?? CountingConvention::PurchaseDayExclusive->value;

        return new self(
            merchant: self::string($data, 'merchant'),
            purchaseDate: self::string($data, 'purchase_date'),
            timezone: self::nullableString($data, 'timezone'),
            receiptNumber: self::nullableString($data, 'receipt_number'),
            currency: self::string($data, 'currency'),
            totalMinor: self::integer($data, 'total_minor'),
            countryRegion: self::string($data, 'country_region'),
            returnPeriodQuantity: self::nullableInteger($data, 'return_period_quantity'),
            returnPeriodUnit: $returnUnit === null ? null : PeriodUnit::from($returnUnit),
            returnCountingConvention: CountingConvention::from($returnConvention),
            warrantyPeriodQuantity: self::nullableInteger($data, 'warranty_period_quantity'),
            warrantyPeriodUnit: $warrantyUnit === null ? null : PeriodUnit::from($warrantyUnit),
            warrantyCountingConvention: CountingConvention::from($warrantyConvention),
            manualReturnDeadline: self::nullableString($data, 'manual_return_deadline'),
            manualReturnReason: self::nullableString($data, 'manual_return_reason'),
            manualWarrantyDeadline: self::nullableString($data, 'manual_warranty_deadline'),
            manualWarrantyReason: self::nullableString($data, 'manual_warranty_reason'),
            notes: self::nullableString($data, 'notes'),
            items: $itemData,
        );
    }

    /** @param array<string, mixed> $data */
    private static function string(array $data, string $key): string
    {
        $value = $data[$key] ?? null;

        if (! is_string($value)) {
            throw new \InvalidArgumentException(sprintf('%s must be a validated string.', $key));
        }

        return $value;
    }

    /** @param array<string, mixed> $data */
    private static function nullableString(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        if ($value === null || is_string($value)) {
            return $value;
        }

        throw new \InvalidArgumentException(sprintf('%s must be a validated nullable string.', $key));
    }

    /** @param array<string, mixed> $data */
    private static function integer(array $data, string $key): int
    {
        $value = $data[$key] ?? null;

        if (! is_int($value)) {
            throw new \InvalidArgumentException(sprintf('%s must be a validated integer.', $key));
        }

        return $value;
    }

    /** @param array<string, mixed> $data */
    private static function nullableInteger(array $data, string $key): ?int
    {
        $value = $data[$key] ?? null;

        if ($value === null || is_int($value)) {
            return $value;
        }

        throw new \InvalidArgumentException(sprintf('%s must be a validated nullable integer.', $key));
    }
}
