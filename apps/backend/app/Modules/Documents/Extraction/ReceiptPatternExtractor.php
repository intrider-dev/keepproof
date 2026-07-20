<?php

declare(strict_types=1);

namespace App\Modules\Documents\Extraction;

use App\Modules\Documents\Contracts\ReceiptDataExtractor;
use App\Modules\Documents\Data\ExtractedDocumentText;
use App\Modules\Documents\Data\ExtractedField;
use App\Modules\Documents\Data\ReceiptExtraction;
use App\Modules\Documents\Data\ReceiptExtractionContext;

final class ReceiptPatternExtractor implements ReceiptDataExtractor
{
    public function extract(
        ExtractedDocumentText $document,
        ReceiptExtractionContext $context,
    ): ReceiptExtraction {
        $fields = [];
        if (! $this->addTextField($fields, $document->text, 'merchant', ['MERCHANT'], '0.9800')) {
            $this->addMerchantHeading($fields, $document->text);
        }
        $this->addTextField($fields, $document->text, 'purchase_date', ['PURCHASE DATE'], '0.9700');
        $this->addTextField($fields, $document->text, 'receipt_number', ['RECEIPT'], '0.9300');
        if (! $this->addTextField($fields, $document->text, 'currency', ['CURRENCY'], '0.9900')) {
            $this->addCurrencyFromTotal($fields, $document->text);
        }
        $this->addMoneyField($fields, $document->text);
        if (! $this->addTextField($fields, $document->text, 'item_name', ['ITEM'], '0.9100')) {
            $this->addItemBeforeQuantity($fields, $document->text);
        }
        $this->addPeriodFields(
            $fields,
            $document->text,
            ['RETURN DAYS', 'RETURN WINDOW'],
            'return',
            'days',
            '0.8600',
        );
        $this->addPeriodFields(
            $fields,
            $document->text,
            ['WARRANTY MONTHS', 'LIMITED WARRANTY'],
            'warranty',
            'months',
            '0.8400',
        );

        return new ReceiptExtraction($fields, 'deterministic-patterns', 'receipt-patterns-v1');
    }

    /**
     * @param  list<ExtractedField>  $fields
     * @param  list<string>  $labels
     */
    private function addTextField(
        array &$fields,
        string $text,
        string $key,
        array $labels,
        string $confidence,
    ): bool {
        $captured = $this->captureFirst($text, $labels);
        if ($captured === null) {
            return false;
        }
        [$value, $label] = $captured;

        $normalized = match ($key) {
            'currency', 'receipt_number' => strtoupper($value),
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

        return true;
    }

    /**
     * @param  list<ExtractedField>  $fields
     */
    private function addMoneyField(array &$fields, string $text): void
    {
        $value = $this->capture($text, 'TOTAL');
        if ($value === null) {
            return;
        }

        $match = [];
        $normalized = null;
        $errors = [];
        if (preg_match(
            '/^(?:[A-Z]{3}\s+)?(?<major>\d{1,13})(?:[.,](?<minor>\d{1,2}))?$/D',
            trim($value),
            $match,
        ) === 1) {
            $minor = str_pad((string) ($match['minor'] ?? ''), 2, '0');
            $normalized = (string) (((int) $match['major'] * 100) + (int) $minor);
        } else {
            $errors[] = 'The extracted total could not be normalized to minor units.';
        }

        $fields[] = new ExtractedField('total_minor', $value, $normalized, '0.9500', 'ocr:TOTAL', $errors);
    }

    /**
     * @param  list<ExtractedField>  $fields
     * @param  list<string>  $labels
     */
    private function addPeriodFields(
        array &$fields,
        string $text,
        array $labels,
        string $prefix,
        string $unit,
        string $confidence,
    ): void {
        $captured = $this->captureFirst($text, $labels);
        if ($captured === null) {
            return;
        }
        [$value, $label] = $captured;

        $matches = [];
        $normalized = preg_match('/^(?<quantity>\d{1,4})(?:\s+[A-Z]+)?$/iD', $value, $matches) === 1
            ? (string) $matches['quantity']
            : null;
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
        $pattern = '/^'.preg_quote($label, '/').'\s*[:,;]?\s+(?<value>[^\r\n]{1,240})$/mi';

        if (preg_match($pattern, $text, $matches) !== 1) {
            return null;
        }

        return trim((string) $matches['value']);
    }

    /** @param list<string> $labels
     * @return array{string, string}|null
     */
    private function captureFirst(string $text, array $labels): ?array
    {
        foreach ($labels as $label) {
            $value = $this->capture($text, $label);
            if ($value !== null) {
                return [$value, $label];
            }
        }

        return null;
    }

    /** @param list<ExtractedField> $fields */
    private function addMerchantHeading(array &$fields, string $text): void
    {
        $lines = preg_split('/\R/u', trim($text));
        if (! is_array($lines)) {
            return;
        }

        foreach ($lines as $line) {
            $value = trim($line);
            if ($value === '' || mb_strlen($value) > 120 || preg_match('/^[A-Z][A-Z0-9 &\'\-.]{2,}$/D', $value) !== 1) {
                continue;
            }

            $fields[] = new ExtractedField('merchant', $value, $value, '0.8200', 'ocr:document-heading');

            return;
        }
    }

    /** @param list<ExtractedField> $fields */
    private function addCurrencyFromTotal(array &$fields, string $text): void
    {
        $matches = [];
        if (preg_match('/^TOTAL\s*[:,;]?\s+(?<currency>[A-Z]{3})\s+\d/mi', $text, $matches) !== 1) {
            return;
        }

        $currency = strtoupper((string) $matches['currency']);
        $fields[] = new ExtractedField('currency', $currency, $currency, '0.9300', 'ocr:TOTAL');
    }

    /** @param list<ExtractedField> $fields */
    private function addItemBeforeQuantity(array &$fields, string $text): void
    {
        $matches = [];
        if (preg_match(
            '/^(?<item>[A-Z][A-Z0-9 &\'\-.]{2,})\R\s*QTY\b/mi',
            str_replace("\r\n", "\n", $text),
            $matches,
        ) !== 1) {
            return;
        }

        $item = trim((string) $matches['item']);
        $fields[] = new ExtractedField('item_name', $item, $item, '0.8000', 'ocr:line-before-quantity');
    }
}
