<?php

declare(strict_types=1);

namespace KeepProof\DeadlineEngine\Value;

use JsonSerializable;

final readonly class PurchaseDeadlineResult implements JsonSerializable
{
    public function __construct(
        public ?DeadlineResult $returnDeadline,
        public ?DeadlineResult $warrantyDeadline,
    ) {
    }

    /** @return array{return: array<string, mixed>|null, warranty: array<string, mixed>|null} */
    public function jsonSerialize(): array
    {
        return [
            'return' => $this->returnDeadline?->jsonSerialize(),
            'warranty' => $this->warrantyDeadline?->jsonSerialize(),
        ];
    }
}
