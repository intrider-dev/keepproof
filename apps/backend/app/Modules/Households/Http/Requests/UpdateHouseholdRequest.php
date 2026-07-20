<?php

declare(strict_types=1);

namespace App\Modules\Households\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateHouseholdRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:120']];
    }

    public function name(): string
    {
        return $this->safe()->string('name')->toString();
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('name'))) {
            $this->merge(['name' => trim($this->string('name')->toString())]);
        }
    }
}
