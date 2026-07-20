<?php

declare(strict_types=1);

namespace App\Modules\Accounts\Actions;

use App\Modules\Accounts\Models\User;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

final class ExportAccountData
{
    public function execute(User $user): string
    {
        $user->load([
            'households.purchases.items',
            'households.purchases.deadlines',
            'households.purchases.documents.fields',
            'notificationPreference',
            'reminders',
        ]);
        $path = tempnam(sys_get_temp_dir(), 'kp_account_');
        if ($path === false) {
            throw new RuntimeException('A temporary account export could not be created.');
        }

        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            @unlink($path);
            throw new RuntimeException('The account export archive could not be opened.');
        }

        try {
            $zip->addFromString(
                'account.json',
                json_encode($this->accountData($user), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            );
            $zip->addFromString(
                'README.txt',
                "KeepProof personal data export\n\n"
                .'The JSON file uses integer minor units for money. Receipt text and files can contain sensitive information. '
                ."Store this archive securely.\n",
            );
            foreach ($user->households as $household) {
                foreach ($household->purchases as $purchase) {
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
                        $zip->addFromString(
                            'households/'.$household->id.'/receipts/'.$document->id.'.'.$extension,
                            $binary,
                        );
                    }
                }
            }
        } catch (\Throwable $exception) {
            $zip->close();
            @unlink($path);

            throw $exception;
        }

        if (! $zip->close()) {
            @unlink($path);
            throw new RuntimeException('The account export archive could not be finalized.');
        }

        return $path;
    }

    /** @return array<string, mixed> */
    private function accountData(User $user): array
    {
        $households = [];
        foreach ($user->households as $household) {
            $purchases = [];
            foreach ($household->purchases as $purchase) {
                $items = [];
                foreach ($purchase->items as $item) {
                    $items[] = [
                        'name' => $item->name,
                        'quantity' => $item->quantity,
                        'unit_price_minor' => $item->unit_price_minor,
                        'serial_number' => $item->serial_number,
                    ];
                }
                $deadlines = [];
                foreach ($purchase->deadlines as $deadline) {
                    $deadlines[] = [
                        'id' => $deadline->id,
                        'kind' => $deadline->kind->value,
                        'due_at' => $deadline->due_at->toAtomString(),
                        'timezone' => $deadline->local_timezone,
                        'source' => $deadline->source->value,
                        'rule_summary' => $deadline->rule_summary,
                        'trace' => $deadline->trace,
                    ];
                }
                $documents = [];
                foreach ($purchase->documents as $document) {
                    $fields = [];
                    foreach ($document->fields as $field) {
                        $fields[] = [
                            'field_key' => $field->field_key,
                            'raw_value' => $field->raw_value,
                            'normalized_value' => $field->normalized_value,
                            'confidence' => $field->confidence,
                            'source' => $field->source,
                            'validation_errors' => $field->validation_errors,
                            'confirmed_value' => $field->confirmed_value,
                        ];
                    }
                    $documents[] = [
                        'id' => $document->id,
                        'state' => $document->state->value,
                        'filename' => $document->original_filename,
                        'mime_type' => $document->mime_type,
                        'raw_ocr_text' => $document->raw_ocr_text,
                        'ocr_provider' => $document->ocr_provider,
                        'extraction_provider' => $document->extraction_provider,
                        'prompt_version' => $document->prompt_version,
                        'fields' => $fields,
                    ];
                }
                $purchases[] = [
                    'id' => $purchase->id,
                    'state' => $purchase->state->value,
                    'merchant' => $purchase->merchant,
                    'purchase_date' => $purchase->purchase_date->format('Y-m-d'),
                    'timezone' => $purchase->timezone,
                    'receipt_number' => $purchase->receipt_number,
                    'currency' => $purchase->currency,
                    'total_minor' => $purchase->total_minor,
                    'country_region' => $purchase->country_region,
                    'notes' => $purchase->notes,
                    'items' => $items,
                    'deadlines' => $deadlines,
                    'documents' => $documents,
                ];
            }
            $households[] = [
                'id' => $household->id,
                'name' => $household->name,
                'is_personal' => $household->is_personal,
                'purchases' => $purchases,
            ];
        }

        return [
            'exported_at' => now()->toAtomString(),
            'account' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'timezone' => $user->timezone,
                'locale' => $user->locale,
                'email_verified_at' => $user->email_verified_at?->toAtomString(),
            ],
            'notification_preferences' => $user->notificationPreference?->only([
                'in_app_enabled',
                'email_enabled',
                'return_intervals',
                'warranty_intervals',
                'quiet_hours_start',
                'quiet_hours_end',
            ]),
            'households' => $households,
        ];
    }
}
