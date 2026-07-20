<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Notifications;

use App\Modules\Notifications\Enums\ReminderChannel;
use App\Modules\Purchases\Models\PurchaseDeadline;
use App\Modules\Shared\Configuration\FrontendUrl;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class DeadlineReminderNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly PurchaseDeadline $deadline,
        private readonly ReminderChannel $channel,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return [$this->channel === ReminderChannel::Email ? 'mail' : 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $purchase = $this->deadline->purchase;
        $kind = $this->deadline->kind->value === 'return' ? 'return window' : 'warranty';
        $configuredName = config('app.name');
        $applicationName = is_string($configuredName) ? $configuredName : 'KeepProof';

        return (new MailMessage)
            ->subject(sprintf('%s: %s deadline is approaching', $applicationName, ucfirst($kind)))
            ->greeting('A KeepProof deadline is approaching')
            ->line(sprintf(
                '%s for %s is due on %s.',
                ucfirst($kind),
                $purchase->merchant,
                $this->localDueDate(),
            ))
            ->action('Review purchase', FrontendUrl::base().'/app/purchases/'.$purchase->id)
            ->line('Check the merchant or manufacturer terms before taking action. KeepProof does not provide legal advice.');
    }

    /** @return array<string, string> */
    public function toArray(object $notifiable): array
    {
        return [
            'purchase_id' => $this->deadline->purchase_id,
            'deadline_id' => $this->deadline->id,
            'kind' => $this->deadline->kind->value,
            'merchant' => $this->deadline->purchase->merchant,
            'due_on' => $this->localDueDate(),
            'message' => sprintf(
                '%s deadline for %s is approaching.',
                ucfirst($this->deadline->kind->value),
                $this->deadline->purchase->merchant,
            ),
        ];
    }

    private function localDueDate(): string
    {
        return $this->deadline->due_at
            ->setTimezone($this->deadline->local_timezone)
            ->format('F j, Y');
    }
}
