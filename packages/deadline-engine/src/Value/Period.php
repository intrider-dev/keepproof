<?php

declare(strict_types=1);

namespace KeepProof\DeadlineEngine\Value;

use JsonSerializable;
use KeepProof\DeadlineEngine\Exception\InvalidPeriod;
use KeepProof\DeadlineEngine\PeriodUnit;

final readonly class Period implements JsonSerializable
{
    public function __construct(public int $quantity, public PeriodUnit $unit)
    {
        $maximum = match ($unit) {
            PeriodUnit::Days => 366_000,
            PeriodUnit::Months => 12_000,
            PeriodUnit::Years => 1_000,
        };

        if ($quantity < 1 || $quantity > $maximum) {
            throw new InvalidPeriod(sprintf(
                '%s periods must be between 1 and %d; received %d.',
                ucfirst($unit->value),
                $maximum,
                $quantity,
            ));
        }
    }

    public static function days(int $quantity): self
    {
        return new self($quantity, PeriodUnit::Days);
    }

    public static function months(int $quantity): self
    {
        return new self($quantity, PeriodUnit::Months);
    }

    public static function years(int $quantity): self
    {
        return new self($quantity, PeriodUnit::Years);
    }

    /** @return array{quantity: int, unit: string} */
    public function jsonSerialize(): array
    {
        return ['quantity' => $this->quantity, 'unit' => $this->unit->value];
    }
}
