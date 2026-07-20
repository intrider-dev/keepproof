<?php

declare(strict_types=1);

namespace App\Modules\Documents\Extraction;

use App\Modules\Documents\Contracts\ReceiptDataExtractor;
use App\Modules\Documents\Data\ExtractedDocumentText;
use App\Modules\Documents\Data\ExtractedField;
use App\Modules\Documents\Data\ReceiptExtraction;

final class ReceiptPatternExtractor implements ReceiptDataExtractor
{
    public function extract(ExtractedDocumentText $document): ReceiptExtraction
    {
        $fields = [];
        $this->addTextField($fields, $document->text, 'merchant', 'MERCHANT', '0.9800');
        $this->addTextField($fields, $document->text, 'purchase_date', 'PURCHASE DATE', '0.9700');
        $this->addTextField($fields, $document->text, 'receipt_number', 'RECEIPT', '0.9300');
        $this->addTextField($fields, $document->text, 'currency', 'CURRENCY', '0.9900');
        $this->addMoneyField($fields, $document->text);
        $this->addTextField($fields, $document->text, 'item_name', 'ITEM', '0.9100');
        $this->addPeriodFields($fields, $document->text, 'RETURN DAYS', 'return', 'days', '0.8600');
        $this->addPeriodFields($fields, $document->text, 'WARRANTY MONTHS', 'warranty', 'months', '0.8400');

        return new ReceiptExtraction($fields, 'deterministic-patterns', 'receipt-patterns-v1');
    }

    /** @param list<ExtractedField> $fields */
    private function addTextField(
        array &$fields,
        string $text,
        string $key,
        string $label,
        string $confidence,
    ): void {
        $value = $this->capture($text, $label);
        if ($value === null) {
            return;
        }

        $normalized = match ($key) {
            'currency' => strtoupper($value),
            default => trim($value),
        };
        $errors = [];
        if ($key === 'purchase_date' && preg_match('/^\d{4}-\d{2}-\d{2}$/D', $normalized) !== 1) {
            $errors[] = 'The extracted date is not in YYYY-MM-DD format.';
        }
        if ($key === 'currency' && preg_match('/^[A-Z]{3}$/D', $normalized) !== 1) {
            $errors[] = 'The extracted currency is not an ISO 4217 code.';
        }

        $fields[] = new ExtractedField($key, $value, $normalized, $confidence, 'ocr:'.$label, $errors);
    }

    /** @param list<ExtractedField> $fields */
    private function addMoneyField(array &$fields, string $text): void
    {
        $value = $this->capture($text, 'TOTAL');
        if ($value === null) {
            return;
        }

        $match = [];
        $normalized = null;
        $errors = [];
        if (preg_match('/^(?<major>\d{1,13})(?:[.,](?<minor>\d{1,2}))?$/D', trim($value), $match) === 1) {
            $minor = str_pad((string) ($match['minor'] ?? ''), 2, '0');
            $normalized = (string) (((int) $match['major'] * 100) + (int) $minor);
        } else {
            $errors[] = 'The extracted total could not be normalized to minor units.';
        }

        $fields[] = new ExtractedField('total_minor', $value, $normalized, '0.9500', 'ocr:TOTAL', $errors);
    }

    /** @param list<ExtractedField> $fields */
    private function addPeriodFields(
        array &$fields,
        string $text,
        string $label,
        string $prefix,
        string $unit,
        string $confidence,
    ): void {
        $value = $this->capture($text, $label);
        if ($value === null) {
            return;
        }

        $normalized = ctype_digit($value) ? $value : null;
        $errors = $normalized === null ? ['The extracted period is not a whole number.'] : [];
        $fields[] = new ExtractedField(
            $prefix.'_period_quantity',
            $value,
            $normalized,
            $confidence,
            'ocr:'.$label,
            $errors,
        );
        $fields[] = new ExtractedField(
            $prefix.'_period_unit',
            $unit,
            $unit,
            $confidence,
            'derived:'.$label,
        );
    }

    private function capture(string $text, string $label): ?string
    {
        $matches = [];
        $pattern = '/^'.preg_quote($label, '/').'\s*:\s*(?<value>[^\r\n]{1,240})$/mi';

        if (preg_match($pattern, $text, $matches) !== 1) {
            return null;
        }

        return trim((string) $matches['value']);
    }
}
