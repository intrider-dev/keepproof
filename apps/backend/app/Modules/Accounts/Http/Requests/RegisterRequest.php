<?php

declare(strict_types=1);

namespace App\Modules\Accounts\Http\Requests;

use App\Modules\Accounts\Data\RegisterAccountData;
use App\Modules\Accounts\Support\SupportedLocales;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

final class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email:strict', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'confirmed', Password::default()],
            'timezone' => ['required', 'string', 'timezone:all'],
            'locale' => ['required', 'string', Rule::in(SupportedLocales::all())],
        ];
    }

    public function payload(): RegisterAccountData
    {
        $validated = $this->safe();

        return new RegisterAccountData(
            name: $validated->string('name')->toString(),
            email: $validated->string('email')->toString(),
            password: $validated->string('password')->toString(),
            timezone: $validated->string('timezone')->toString(),
            locale: $validated->string('locale')->toString(),
        );
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => is_string($this->input('name')) ? trim($this->string('name')->toString()) : $this->input('name'),
            'email' => is_string($this->input('email')) ? mb_strtolower(trim($this->string('email')->toString())) : $this->input('email'),
        ]);
    }
}
