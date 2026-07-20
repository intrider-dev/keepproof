<?php

declare(strict_types=1);

namespace App\Modules\Documents\Http\Requests;

use App\Modules\Purchases\Http\Requests\PurchaseDetailsRequest;
use Illuminate\Validation\ValidationException;

final class ConfirmExtractionRequest extends PurchaseDetailsRequest
{
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
            throw new \LogicException('A correlation ID must be assigned before extraction confirmation.');
        }

        return $value;
    }
}
