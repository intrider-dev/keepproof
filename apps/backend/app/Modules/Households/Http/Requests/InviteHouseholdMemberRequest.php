<?php

declare(strict_types=1);

namespace App\Modules\Households\Http\Requests;

use App\Modules\Households\Enums\HouseholdRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class InviteHouseholdMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email:strict', 'max:255'],
            'role' => ['sometimes', 'required', Rule::enum(HouseholdRole::class)->only([HouseholdRole::Member])],
        ];
    }

    protected function prepareForValidation(): void
    {
        $updates = ['role' => $this->input('role', HouseholdRole::Member->value)];

        if (is_string($this->input('email'))) {
            $updates['email'] = mb_strtolower(trim($this->string('email')->toString()));
        }

        $this->merge($updates);
    }
}
