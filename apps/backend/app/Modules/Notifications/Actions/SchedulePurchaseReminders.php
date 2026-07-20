<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Actions;

use App\Modules\Accounts\Models\User;
use App\Modules\Notifications\Enums\ReminderChannel;
use App\Modules\Notifications\Enums\ReminderStatus;
use App\Modules\Notifications\Models\NotificationPreference;
use App\Modules\Notifications\Models\Reminder;
use App\Modules\Purchases\Models\Purchase;
use App\Modules\Purchases\Models\PurchaseDeadline;
use Carbon\CarbonImmutable;
use KeepProof\DeadlineEngine\DeadlineKind;

final class SchedulePurchaseReminders
{
    public function execute(Purchase $purchase): void
    {
        $purchase->loadMissing(['deadlines', 'household.members']);
        foreach ($purchase->deadlines->where('active', true) as $deadline) {
            foreach ($purchase->household->members as $user) {
                $this->scheduleForUser($deadline, $user);
            }
        }
    }

    public function scheduleForUser(PurchaseDeadline $deadline, User $user): void
    {
        $preferences = NotificationPreference::query()->firstOrCreate(
            ['user_id' => $user->id],
            [
                'in_app_enabled' => true,
                'email_enabled' => true,
                'return_intervals' => [7, 3, 1],
                'warranty_intervals' => [30, 7],
            ],
        );
        $intervals = $deadline->kind === DeadlineKind::Return
            ? $preferences->return_intervals
            : $preferences->warranty_intervals;
        $channels = [];
        if ($preferences->in_app_enabled) {
            $channels[] = ReminderChannel::InApp;
        }
        if ($preferences->email_enabled && $user->hasVerifiedEmail()) {
            $channels[] = ReminderChannel::Email;
        }

        $desiredKeys = [];
        foreach ($intervals as $interval) {
            foreach ($channels as $channel) {
                $remindAt = $this->remindAt($deadline, $user, $interval);
                if ($remindAt->isPast()) {
                    continue;
                }

                $deduplicationKey = hash('sha256', implode('|', [
                    $deadline->id,
                    $user->id,
                    $channel->value,
                    (string) $interval,
                    $deadline->due_at->toISOString(),
                ]));
                $desiredKeys[] = $deduplicationKey;
                $existing = Reminder::query()->where('deduplication_key', $deduplicationKey)->first();
                if ($existing?->status === ReminderStatus::Sent) {
                    continue;
                }

                Reminder::query()->updateOrCreate(
                    ['deduplication_key' => $deduplicationKey],
                    [
                        'purchase_deadline_id' => $deadline->id,
                        'household_id' => $deadline->household_id,
                        'user_id' => $user->id,
                        'channel' => $channel,
                        'interval_days' => $interval,
                        'remind_at' => $remindAt,
                        'status' => ReminderStatus::Scheduled,
                        'attempts' => 0,
                        'sent_at' => null,
                        'last_error_code' => null,
                    ],
                );
            }
        }

        Reminder::query()
            ->where('purchase_deadline_id', $deadline->id)
            ->where('user_id', $user->id)
            ->whereIn('status', [ReminderStatus::Scheduled->value, ReminderStatus::Failed->value])
            ->when($desiredKeys !== [], static fn ($query) => $query->whereNotIn('deduplication_key', $desiredKeys))
            ->when($desiredKeys === [], static fn ($query) => $query)
            ->update(['status' => ReminderStatus::Cancelled]);
    }

    private function remindAt(PurchaseDeadline $deadline, User $user, int $interval): CarbonImmutable
    {
        return $deadline->due_at
            ->setTimezone($user->timezone)
            ->subDays($interval)
            ->setTime(9, 0)
            ->utc();
    }
}
