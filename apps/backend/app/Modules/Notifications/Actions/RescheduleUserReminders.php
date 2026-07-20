<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Actions;

use App\Modules\Accounts\Models\User;
use App\Modules\Purchases\Models\PurchaseDeadline;

final readonly class RescheduleUserReminders
{
    public function __construct(private SchedulePurchaseReminders $scheduleReminders) {}

    public function execute(User $user): void
    {
        PurchaseDeadline::query()
            ->where('active', true)
            ->where('due_at', '>', now())
            ->whereIn('household_id', $user->households()->select('households.id'))
            ->chunkById(100, function ($deadlines) use ($user): void {
                foreach ($deadlines as $deadline) {
                    $this->scheduleReminders->scheduleForUser($deadline, $user);
                }
            });
    }
}
