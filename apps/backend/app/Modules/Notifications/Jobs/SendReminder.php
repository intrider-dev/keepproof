<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Jobs;

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
                ReminderStatus::Cancelled,
            ], true)) {
                return null;
            }
            if ($locked->remind_at->isFuture()) {
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
            $reminder->user->notify(new DeadlineReminderNotification($reminder->deadline, $reminder->channel));
            $reminder->update([
                'status' => ReminderStatus::Sent,
                'sent_at' => now(),
                'last_error_code' => null,
            ]);
        } catch (Throwable $exception) {
            $reminder->update([
                'status' => ReminderStatus::Failed,
                'last_error_code' => 'notification_delivery_failed',
            ]);

            throw $exception;
        }
    }

    public function failed(?Throwable $exception): void
    {
        Reminder::query()->whereKey($this->reminderId)->update([
            'status' => ReminderStatus::Failed,
            'last_error_code' => 'notification_delivery_exhausted',
        ]);
    }
}
