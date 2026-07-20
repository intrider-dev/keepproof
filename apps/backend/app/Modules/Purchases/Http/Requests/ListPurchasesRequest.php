<?php

declare(strict_types=1);

namespace App\Modules\Purchases\Http\Requests;

use App\Modules\Purchases\Enums\PurchaseState;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ListPurchasesRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'search' => ['sometimes', 'string', 'max:160'],
            'status' => ['sometimes', Rule::enum(PurchaseState::class)],
            'merchant' => ['sometimes', 'string', 'max:160'],
            'date_from' => ['sometimes', 'date_format:Y-m-d'],
            'date_to' => ['sometimes', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'deadline_before' => ['sometimes', 'date_format:Y-m-d'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }

    public function stringValue(string $key): ?string
    {
        $value = $this->validated($key);

        return is_string($value) ? $value : null;
    }

    public function perPage(): int
    {
        return $this->integer('per_page', 20);
    }
}
