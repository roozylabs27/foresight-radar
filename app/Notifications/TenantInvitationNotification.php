<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

/**
 * The only place the raw invitation token travels: the database keeps its hash.
 * The link carries its own expiry check, so a late email shows "expired".
 */
class TenantInvitationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        public string $acceptUrl,
        public string $organization,
        public string $inviterName,
        public string $roleName,
        public Carbon $expiresAt,
    ) {
        $this->afterCommit();
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Undangan bergabung dengan {$this->organization} di Foresight Radar")
            ->line("{$this->inviterName} mengundang Anda bergabung dengan workspace {$this->organization} sebagai {$this->roleName}.")
            ->action('Terima Undangan', $this->acceptUrl)
            ->line('Tautan ini berlaku hingga '.$this->expiresAt->translatedFormat('d F Y H:i').' dan hanya dapat dipakai sekali.')
            ->line('Abaikan email ini jika Anda tidak mengharapkan undangan.');
    }
}
