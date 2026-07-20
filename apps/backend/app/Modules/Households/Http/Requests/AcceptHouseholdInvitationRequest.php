<?php

declare(strict_types=1);

namespace App\Modules\Households\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class AcceptHouseholdInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['token' => ['required', 'string', 'min:40', 'max:128']];
    }
}
