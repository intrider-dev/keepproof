<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Actions;

use App\Modules\Notifications\Enums\ReminderChannel;
use App\Modules\Notifications\Enums\ReminderStatus;
use App\Modules\Notifications\Jobs\SendReminder;
use App\Modules\Notifications\Models\Reminder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

final class DispatchDueReminders
{
    private const DELIVERY_LEASE_SECONDS = 60;

    public function execute(): void
    {
        $this->reconcileInterruptedDeliveries();

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

    private function reconcileInterruptedDeliveries(): void
    {
        $cutoff = Carbon::now()->subSeconds(self::DELIVERY_LEASE_SECONDS);
        $emailCount = Reminder::query()
            ->where('status', ReminderStatus::Sending)
            ->where('channel', ReminderChannel::Email)
            ->where('updated_at', '<=', $cutoff)
            ->update([
                'status' => ReminderStatus::DeliveryUnknown,
                'last_error_code' => 'email_delivery_unknown_after_interruption',
            ]);
        $inAppCount = Reminder::query()
            ->where('status', ReminderStatus::Sending)
            ->where('channel', ReminderChannel::InApp)
            ->where('updated_at', '<=', $cutoff)
            ->update([
                'status' => ReminderStatus::Failed,
                'last_error_code' => 'in_app_delivery_interrupted',
            ]);

        if ($emailCount > 0 || $inAppCount > 0) {
            Log::warning('reminder.interrupted_delivery_reconciled', [
                'email_delivery_unknown_count' => $emailCount,
                'in_app_retry_count' => $inAppCount,
            ]);
        }
    }
}
