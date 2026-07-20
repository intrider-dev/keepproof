<?php

declare(strict_types=1);

namespace App\Modules\Accounts\Http\Requests;

use App\Modules\Accounts\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() instanceof User;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'email' => [
                'sometimes',
                'required',
                'string',
                'email:strict',
                'max:255',
                Rule::unique('users', 'email')->ignore($this->user()?->getKey()),
            ],
            'timezone' => ['sometimes', 'required', 'string', 'timezone:all'],
            'locale' => ['sometimes', 'required', 'string', Rule::in(config('app.supported_locales'))],
        ];
    }

    protected function prepareForValidation(): void
    {
        $updates = [];

        if ($this->has('name') && is_string($this->input('name'))) {
            $updates['name'] = trim($this->string('name')->toString());
        }

        if ($this->has('email') && is_string($this->input('email'))) {
            $updates['email'] = mb_strtolower(trim($this->string('email')->toString()));
        }

        $this->merge($updates);
    }
}
