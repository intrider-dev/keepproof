<?php

declare(strict_types=1);

namespace App\Modules\Purchases\Data;

final readonly class PurchaseItemData
{
    public function __construct(
        public string $name,
        public int $quantity,
        public ?int $unitPriceMinor,
        public ?string $serialNumber,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            name: self::string($data, 'name'),
            quantity: self::integer($data, 'quantity'),
            unitPriceMinor: self::nullableInteger($data, 'unit_price_minor'),
            serialNumber: self::nullableString($data, 'serial_number'),
        );
    }

    /** @return array{name: string, quantity: int, unit_price_minor: int|null, serial_number: string|null} */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'quantity' => $this->quantity,
            'unit_price_minor' => $this->unitPriceMinor,
            'serial_number' => $this->serialNumber,
        ];
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
