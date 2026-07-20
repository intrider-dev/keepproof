<?php

declare(strict_types=1);

namespace App\Modules\Accounts\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email:strict', 'max:255'],
            'password' => ['required', 'string'],
        ];
    }

    /** @return array{email: string, password: string} */
    public function credentials(): array
    {
        $validated = $this->safe();

        return [
            'email' => $validated->string('email')->toString(),
            'password' => $validated->string('password')->toString(),
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower(trim($this->string('email')->toString()))]);
        }
    }
}
