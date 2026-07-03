<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Queued email inviting a newly created account to choose its first password.
 * The link points at the frontend, which reads the token and email and calls
 * the set-password endpoint to complete the flow.
 */
class SetPasswordNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private string $token,
        private string $email,
    ) {}

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
            ->subject('Set your password')
            ->line('An account was created for you at the atelier.')
            ->action('Set your password', $this->url())
            ->line('If you were not expecting this, you can safely ignore this email.');
    }

    private function url(): string
    {
        $base = config('app.frontend_url');
        $base = is_string($base) ? rtrim($base, '/') : '';

        return $base.'/set-password?token='.$this->token.'&email='.urlencode($this->email);
    }
}
