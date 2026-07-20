<?php

declare(strict_types=1);

namespace KeepProof\DeadlineEngine\Value;

use JsonSerializable;
use KeepProof\DeadlineEngine\DeadlineKind;
use KeepProof\DeadlineEngine\DeadlineSource;

/**
 * JSON shape:
 * array{
 *   kind: string,
 *   source: string,
 *   effective_deadline: array{local: string, timezone: string, utc: string},
 *   calculated_deadline: array{local: string, timezone: string, utc: string},
 *   rule_summary: string,
 *   trace: list<array{code: string, explanation: string, context: array<string, bool|int|string|null>}>
 * }
 */
final readonly class DeadlineResult implements JsonSerializable
{
    /**
     * @param non-empty-list<RuleTraceStep> $trace
     */
    public function __construct(
        public DeadlineKind $kind,
        public LocalDateTime $effectiveDeadline,
        public LocalDateTime $calculatedDeadline,
        public DeadlineSource $source,
        public string $ruleSummary,
        public array $trace,
    ) {
    }

    /**
     * @return array{
     *   kind: string,
     *   source: string,
     *   effective_deadline: array{local: string, timezone: string, utc: string},
     *   calculated_deadline: array{local: string, timezone: string, utc: string},
     *   rule_summary: string,
     *   trace: list<array{code: string, explanation: string, context: array<string, bool|int|string|null>}>
     * }
     */
    public function jsonSerialize(): array
    {
        return [
            'kind' => $this->kind->value,
            'source' => $this->source->value,
            'effective_deadline' => $this->effectiveDeadline->jsonSerialize(),
            'calculated_deadline' => $this->calculatedDeadline->jsonSerialize(),
            'rule_summary' => $this->ruleSummary,
            'trace' => array_map(
                static fn (RuleTraceStep $step): array => $step->jsonSerialize(),
                $this->trace,
            ),
        ];
    }
}
