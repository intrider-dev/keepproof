<?php

declare(strict_types=1);

namespace App\Modules\Exports\Actions;

use App\Modules\Purchases\Models\Purchase;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

final class ExportClaimPackage
{
    public function execute(Purchase $purchase): string
    {
        $purchase->loadMissing(['items', 'deadlines', 'documents.fields']);
        $path = tempnam(sys_get_temp_dir(), 'kp_claim_');
        if ($path === false) {
            throw new RuntimeException('A temporary claim package could not be created.');
        }

        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            @unlink($path);
            throw new RuntimeException('The claim package archive could not be opened.');
        }

        try {
            $zip->addFromString('purchase.json', $this->json($this->purchaseData($purchase)));
            $zip->addFromString('extraction-fields.json', $this->json($this->extractionData($purchase)));
            $zip->addFromString(
                'README.txt',
                "KeepProof claim package\n\n"
                .'This archive contains user-supplied purchase details and stored proof documents. '
                ."KeepProof does not provide legal advice and does not determine rights under local law.\n",
            );

            foreach ($purchase->documents as $document) {
                if (! $document->retain_original) {
                    continue;
                }

                $binary = Storage::disk($document->storage_disk)->get($document->storage_key);
                if ($binary === null) {
                    throw new RuntimeException('A receipt document is temporarily unavailable.');
                }
                $extension = match ($document->mime_type) {
                    'image/jpeg' => 'jpg',
                    'image/png' => 'png',
                    'application/pdf' => 'pdf',
                    default => 'bin',
                };
                $zip->addFromString('receipts/'.$document->id.'.'.$extension, $binary);
            }
        } catch (\Throwable $exception) {
            $zip->close();
            @unlink($path);

            throw $exception;
        }

        if (! $zip->close()) {
            @unlink($path);
            throw new RuntimeException('The claim package archive could not be finalized.');
        }

        return $path;
    }

    /** @return array<string, mixed> */
    private function purchaseData(Purchase $purchase): array
    {
        return [
            'id' => $purchase->id,
            'merchant' => $purchase->merchant,
            'purchase_date' => $purchase->purchase_date->format('Y-m-d'),
            'timezone' => $purchase->timezone,
            'receipt_number' => $purchase->receipt_number,
            'currency' => $purchase->currency,
            'total_minor' => $purchase->total_minor,
            'country_region' => $purchase->country_region,
            'notes' => $purchase->notes,
            'items' => $purchase->items->map(static fn ($item): array => [
                'name' => $item->name,
                'quantity' => $item->quantity,
                'unit_price_minor' => $item->unit_price_minor,
                'serial_number' => $item->serial_number,
            ])->all(),
            'deadlines' => $purchase->deadlines->map(static fn ($deadline): array => [
                'kind' => $deadline->kind->value,
                'due_at' => $deadline->due_at->toAtomString(),
                'timezone' => $deadline->local_timezone,
                'source' => $deadline->source->value,
                'rule_summary' => $deadline->rule_summary,
                'trace' => $deadline->trace,
            ])->all(),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function extractionData(Purchase $purchase): array
    {
        $documents = [];
        foreach ($purchase->documents as $document) {
            $fields = [];
            foreach ($document->fields as $field) {
                $fields[] = [
                    'key' => $field->field_key,
                    'normalized_value' => $field->normalized_value,
                    'confidence' => $field->confidence,
                    'source' => $field->source,
                    'validation_errors' => $field->validation_errors,
                    'confirmed_value' => $field->confirmed_value,
                ];
            }
            $documents[] = [
                'document_id' => $document->id,
                'state' => $document->state->value,
                'filename' => $document->original_filename,
                'fields' => $fields,
            ];
        }

        return $documents;
    }

    /** @param array<string, mixed>|list<array<string, mixed>> $value */
    private function json(array $value): string
    {
        return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}
