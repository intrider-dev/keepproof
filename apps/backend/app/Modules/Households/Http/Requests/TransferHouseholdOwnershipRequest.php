<?php

declare(strict_types=1);

namespace App\Modules\Households\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class TransferHouseholdOwnershipRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['member_user_id' => ['required', 'ulid']];
    }

    public function memberUserId(): string
    {
        $value = $this->validated('member_user_id');

        return is_string($value) ? $value : throw new \LogicException('A validated member user ID is required.');
    }
}
