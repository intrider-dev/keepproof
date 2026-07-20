<?php

declare(strict_types=1);

namespace KeepProof\DeadlineEngine\Value;

use JsonSerializable;
use KeepProof\DeadlineEngine\RuleCode;

final readonly class RuleTraceStep implements JsonSerializable
{
    /**
     * @param array<string, bool|int|string|null> $context
     */
    public function __construct(
        public RuleCode $code,
        public string $explanation,
        public array $context = [],
    ) {
    }

    /** @return array{code: string, explanation: string, context: array<string, bool|int|string|null>} */
    public function jsonSerialize(): array
    {
        return [
            'code' => $this->code->value,
            'explanation' => $this->explanation,
            'context' => $this->context,
        ];
    }
}
