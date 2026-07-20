<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Notifications\Actions\RescheduleUserReminders;
use App\Modules\Notifications\Http\Requests\UpdateNotificationPreferencesRequest;
use App\Modules\Notifications\Http\Resources\NotificationPreferenceResource;
use App\Modules\Notifications\Models\NotificationPreference;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class NotificationPreferenceController extends Controller
{
    public function show(Request $request): NotificationPreferenceResource
    {
        $user = $this->authenticatedUser($request);

        return new NotificationPreferenceResource($this->preferences($user->id)->load('user'));
    }

    public function update(
        UpdateNotificationPreferencesRequest $request,
        RescheduleUserReminders $reschedule,
        AuditRecorder $audit,
    ): NotificationPreferenceResource {
        $user = $this->authenticatedUser($request);
        $preferences = DB::transaction(function () use ($request, $user, $audit): NotificationPreference {
            $preferences = $this->preferences($user->id);
            $preferences->update($request->preferences());
            $audit->record('notification_preferences.updated', $user, subject: $preferences);

            return $preferences;
        });
        $reschedule->execute($user);

        return new NotificationPreferenceResource($preferences->refresh()->load('user'));
    }

    private function preferences(string $userId): NotificationPreference
    {
        return NotificationPreference::query()->firstOrCreate(
            ['user_id' => $userId],
            [
                'in_app_enabled' => true,
                'email_enabled' => true,
                'return_intervals' => [7, 3, 1],
                'warranty_intervals' => [30, 7],
            ],
        );
    }
}
