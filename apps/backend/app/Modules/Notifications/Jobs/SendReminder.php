<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Jobs;

use App\Modules\Notifications\Enums\ReminderChannel;
use App\Modules\Notifications\Enums\ReminderStatus;
use App\Modules\Notifications\Models\Reminder;
use App\Modules\Notifications\Notifications\DeadlineReminderNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Ramsey\Uuid\Uuid;
use Throwable;

final class SendReminder implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 30;

    public function __construct(public readonly string $reminderId) {}

    /** @return list<int> */
    public function backoff(): array
    {
        return [30, 120, 600];
    }

    /** @return list<WithoutOverlapping> */
    public function middleware(): array
    {
        return [(new WithoutOverlapping('reminder:'.$this->reminderId))->releaseAfter(20)->expireAfter(60)];
    }

    public function handle(): void
    {
        $reminder = DB::transaction(function (): ?Reminder {
            $locked = Reminder::query()->lockForUpdate()->find($this->reminderId);
            if ($locked === null || in_array($locked->status, [
                ReminderStatus::Sent,
                ReminderStatus::DeliveryUnknown,
                ReminderStatus::Cancelled,
            ], true)) {
                return null;
            }
            if ($locked->remind_at->isFuture()) {
                return null;
            }

            if ($locked->channel === ReminderChannel::InApp
                && DB::table('notifications')->where('id', $this->notificationId())->exists()) {
                $locked->update([
                    'status' => ReminderStatus::Sent,
                    'sent_at' => $locked->sent_at ?? now(),
                    'last_error_code' => null,
                ]);

                return null;
            }

            $locked->update([
                'status' => ReminderStatus::Sending,
                'attempts' => $locked->attempts + 1,
                'last_error_code' => null,
            ]);

            return $locked->load(['user', 'deadline.purchase']);
        });
        if ($reminder === null) {
            return;
        }

        try {
            $notification = new DeadlineReminderNotification($reminder->deadline, $reminder->channel);
            $notification->id = $this->notificationId();
            $reminder->user->notify($notification);
            $reminder->update([
                'status' => ReminderStatus::Sent,
                'sent_at' => now(),
                'last_error_code' => null,
            ]);
        } catch (Throwable $exception) {
            if ($reminder->channel === ReminderChannel::Email) {
                $reminder->update([
                    'status' => ReminderStatus::DeliveryUnknown,
                    'last_error_code' => 'email_delivery_unknown',
                ]);
                Log::warning('reminder.email_delivery_unknown', [
                    'reminder_id' => $reminder->id,
                    'exception' => $exception::class,
                ]);

                return;
            }

            $reminder->update([
                'status' => ReminderStatus::Failed,
                'last_error_code' => 'notification_delivery_failed',
            ]);

            throw $exception;
        }
    }

    public function failed(?Throwable $exception): void
    {
        $reminder = Reminder::query()->find($this->reminderId);
        if ($reminder === null || $reminder->status === ReminderStatus::DeliveryUnknown) {
            return;
        }
        if ($reminder->channel === ReminderChannel::Email && $reminder->status === ReminderStatus::Sending) {
            $reminder->update([
                'status' => ReminderStatus::DeliveryUnknown,
                'last_error_code' => 'email_delivery_unknown_after_interruption',
            ]);

            return;
        }

        $reminder->update([
            'status' => ReminderStatus::Failed,
            'last_error_code' => 'notification_delivery_exhausted',
        ]);
    }

    private function notificationId(): string
    {
        return Uuid::uuid5(Uuid::NAMESPACE_URL, 'https://keepproof.local/reminders/'.$this->reminderId)
            ->toString();
    }
}
