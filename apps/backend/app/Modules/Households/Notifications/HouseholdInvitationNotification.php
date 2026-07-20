<?php

declare(strict_types=1);

namespace App\Modules\Households\Notifications;

use App\Modules\Households\Models\Household;
use App\Modules\Households\Models\HouseholdInvitation;
use App\Modules\Shared\Configuration\FrontendUrl;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class HouseholdInvitationNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly HouseholdInvitation $invitation,
        private readonly Household $household,
        private readonly string $plainToken,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Invitation to join a KeepProof household')
            ->greeting('You have been invited to KeepProof')
            ->line(sprintf('Join the "%s" household to manage purchase records together.', $this->household->name))
            ->action('Review invitation', $this->acceptUrl())
            ->line('This invitation expires in 72 hours. Ignore this email if you were not expecting it.');
    }

    public function acceptUrl(): string
    {
        return sprintf(
            '%s/invitations/%s?token=%s',
            FrontendUrl::base(),
            $this->invitation->id,
            rawurlencode($this->plainToken),
        );
    }
}
