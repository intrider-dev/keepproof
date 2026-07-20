<?php

declare(strict_types=1);

namespace App\Modules\Documents\Data;

final readonly class ExtractedField
{
    /** @param list<string> $validationErrors */
    public function __construct(
        public string $key,
        public ?string $value,
        public ?string $normalizedValue,
        public ?string $confidence,
        public string $source,
        public array $validationErrors = [],
    ) {}

    /** @return array<string, mixed> */
    public function toPersistence(): array
    {
        return [
            'field_key' => $this->key,
            'raw_value' => $this->value,
            'normalized_value' => $this->normalizedValue,
            'confidence' => $this->confidence,
            'source' => $this->source,
            'validation_errors' => $this->validationErrors,
        ];
    }
}
