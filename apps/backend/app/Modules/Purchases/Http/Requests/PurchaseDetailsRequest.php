<?php

declare(strict_types=1);

namespace App\Modules\Purchases\Http\Requests;

use App\Modules\Purchases\Data\PurchaseData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use KeepProof\DeadlineEngine\CountingConvention;
use KeepProof\DeadlineEngine\PeriodUnit;

abstract class PurchaseDetailsRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'merchant' => ['required', 'string', 'max:160'],
            'purchase_date' => ['required', 'date_format:Y-m-d'],
            'timezone' => ['sometimes', 'string', 'timezone:all'],
            'receipt_number' => ['nullable', 'string', 'max:120'],
            'currency' => ['required', 'string', 'size:3', 'regex:/^[A-Z]{3}$/'],
            'total_minor' => ['required', 'integer', 'min:0', 'max:9000000000000000'],
            'country_region' => ['required', 'string', 'max:80'],
            'return_period_quantity' => ['nullable', 'required_with:return_period_unit,manual_return_deadline', 'integer', 'min:1', 'max:1000'],
            'return_period_unit' => ['nullable', 'required_with:return_period_quantity', Rule::enum(PeriodUnit::class)],
            'return_counting_convention' => ['sometimes', Rule::enum(CountingConvention::class)],
            'warranty_period_quantity' => ['nullable', 'required_with:warranty_period_unit,manual_warranty_deadline', 'integer', 'min:1', 'max:1000'],
            'warranty_period_unit' => ['nullable', 'required_with:warranty_period_quantity', Rule::enum(PeriodUnit::class)],
            'warranty_counting_convention' => ['sometimes', Rule::enum(CountingConvention::class)],
            'manual_return_deadline' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:purchase_date'],
            'manual_return_reason' => ['nullable', 'required_with:manual_return_deadline', 'string', 'max:240'],
            'manual_warranty_deadline' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:purchase_date'],
            'manual_warranty_reason' => ['nullable', 'required_with:manual_warranty_deadline', 'string', 'max:240'],
            'notes' => ['nullable', 'string', 'max:10000'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.name' => ['required', 'string', 'max:200'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'items.*.unit_price_minor' => ['nullable', 'integer', 'min:0', 'max:9000000000000000'],
            'items.*.serial_number' => ['nullable', 'string', 'max:160'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $currency = $this->input('currency');

        if (is_string($currency)) {
            $this->merge(['currency' => strtoupper(trim($currency))]);
        }
    }

    public function purchaseData(): PurchaseData
    {
        return PurchaseData::fromValidated($this->validated());
    }
}
