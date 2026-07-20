<?php

declare(strict_types=1);

namespace App\Modules\Documents\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\ValidationException;

final class UploadReceiptRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'receipt' => ['required', File::types(['jpg', 'jpeg', 'png', 'pdf'])->max('10mb')],
            'purchase_id' => ['nullable', 'ulid'],
            'external_ai_consent' => ['sometimes', 'boolean'],
        ];
    }

    public function receipt(): UploadedFile
    {
        $file = $this->file('receipt');
        if (! $file instanceof UploadedFile) {
            throw ValidationException::withMessages(['receipt' => ['A receipt file is required.']]);
        }

        return $file;
    }

    public function purchaseId(): ?string
    {
        $value = $this->validated('purchase_id');

        return is_string($value) ? $value : null;
    }

    public function externalAiConsentGranted(): bool
    {
        return $this->boolean('external_ai_consent');
    }

    public function idempotencyKey(): string
    {
        $value = $this->header('Idempotency-Key');
        if (! is_string($value) || preg_match('/^[A-Za-z0-9._:-]{8,200}$/D', $value) !== 1) {
            throw ValidationException::withMessages([
                'idempotency_key' => ['Idempotency-Key must contain 8 to 200 safe ASCII characters.'],
            ]);
        }

        return $value;
    }

    public function correlationId(): string
    {
        $value = $this->attributes->get('correlation_id');

        if (! is_string($value)) {
            throw new \LogicException('A correlation ID must be assigned before receipt upload.');
        }

        return $value;
    }
}
