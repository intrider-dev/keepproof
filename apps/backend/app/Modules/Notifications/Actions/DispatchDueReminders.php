<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Actions;

use App\Modules\Notifications\Enums\ReminderStatus;
use App\Modules\Notifications\Jobs\SendReminder;
use App\Modules\Notifications\Models\Reminder;
use Illuminate\Support\Facades\Log;
use Throwable;

final class DispatchDueReminders
{
    public function execute(): void
    {
        Reminder::query()
            ->whereIn('status', [ReminderStatus::Scheduled->value, ReminderStatus::Failed->value])
            ->where('attempts', '<', 3)
            ->where('remind_at', '<=', now())
            ->orderBy('id')
            ->chunkById(100, function ($reminders): void {
                foreach ($reminders as $reminder) {
                    try {
                        SendReminder::dispatch($reminder->id)->onQueue('notifications');
                    } catch (Throwable $exception) {
                        Log::warning('reminder.dispatch_unavailable', [
                            'reminder_id' => $reminder->id,
                            'exception' => $exception::class,
                        ]);
                    }
                }
            });
    }
}
