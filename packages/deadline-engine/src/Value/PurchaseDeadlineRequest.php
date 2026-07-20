<?php

declare(strict_types=1);

namespace KeepProof\DeadlineEngine\Value;

use KeepProof\DeadlineEngine\Exception\InvalidDeadlineRequest;

final readonly class PurchaseDeadlineRequest
{
    public function __construct(
        public LocalDateTime $purchasedAt,
        public ?DeadlineTerms $returnTerms,
        public ?DeadlineTerms $warrantyTerms,
    ) {
        if ($returnTerms === null && $warrantyTerms === null) {
            throw new InvalidDeadlineRequest('At least one return or warranty deadline must be requested.');
        }
    }
}
