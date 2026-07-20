<?php

declare(strict_types=1);

namespace KeepProof\DeadlineEngine\Value;

use KeepProof\DeadlineEngine\Exception\InvalidManualOverride;

final readonly class ManualOverride
{
    public string $reason;

    public function __construct(public LocalDateTime $deadline, string $reason)
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw new InvalidManualOverride('A manual deadline override requires a reason.');
        }

        if (strlen($reason) > 500) {
            throw new InvalidManualOverride('A manual deadline override reason cannot exceed 500 UTF-8 bytes.');
        }

        $this->reason = $reason;
    }
}
