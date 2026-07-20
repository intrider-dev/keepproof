<?php

declare(strict_types=1);

namespace App\Modules\Accounts\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

final class ResetPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email:strict', 'max:255'],
            'password' => ['required', 'confirmed', Password::default()],
        ];
    }

    /** @return array{email: string, password: string, password_confirmation: string, token: string} */
    public function credentials(): array
    {
        $validated = $this->safe();

        return [
            'email' => $validated->string('email')->toString(),
            'password' => $validated->string('password')->toString(),
            'password_confirmation' => $validated->string('password_confirmation')->toString(),
            'token' => $validated->string('token')->toString(),
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower(trim($this->string('email')->toString()))]);
        }
    }
}
