<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateNotificationPreferencesRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'in_app_enabled' => ['required', 'boolean'],
            'email_enabled' => ['required', 'boolean'],
            'return_intervals' => ['present', 'array', 'max:8'],
            'return_intervals.*' => ['integer', 'min:1', 'max:365', 'distinct'],
            'warranty_intervals' => ['present', 'array', 'max:8'],
            'warranty_intervals.*' => ['integer', 'min:1', 'max:365', 'distinct'],
            'quiet_hours_start' => ['nullable', 'date_format:H:i', 'required_with:quiet_hours_end'],
            'quiet_hours_end' => ['nullable', 'date_format:H:i', 'required_with:quiet_hours_start'],
        ];
    }

    /** @return array<string, mixed> */
    public function preferences(): array
    {
        $data = $this->validated();
        $data['return_intervals'] = $this->sortedIntervals($data['return_intervals'] ?? []);
        $data['warranty_intervals'] = $this->sortedIntervals($data['warranty_intervals'] ?? []);

        return $data;
    }

    /** @return list<int> */
    private function sortedIntervals(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $intervals = array_values(array_filter($value, is_int(...)));
        rsort($intervals, SORT_NUMERIC);

        return $intervals;
    }
}
