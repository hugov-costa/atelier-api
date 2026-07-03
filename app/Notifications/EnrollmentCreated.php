<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Enrollment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Queued welcome sent to a student when their enrollment is created, carrying the
 * annual fee amount and due date. Delivered by email and stored in-app.
 */
class EnrollmentCreated extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private Enrollment $enrollment) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type'        => 'enrollment_created',
            'message'     => 'Sua matrícula no ateliê foi criada.',
            'amount'      => $this->enrollment->annual_fee,
            'due_date'    => $this->enrollment->annual_fee_due_date?->toDateString(),
            'resource_id' => $this->enrollment->ulid,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Bem-vindo(a) ao ateliê')
            ->line('Sua matrícula foi criada com sucesso.')
            ->line(sprintf(
                'Taxa de matrícula anual: R$ %s.',
                number_format($this->enrollment->annual_fee / 100, 2, ',', '.'),
            ))
            ->action('Acessar minha conta', $this->frontendUrl())
            ->line('Estamos felizes em ter você conosco!');
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    private function frontendUrl(): string
    {
        $base = config('app.frontend_url');

        return is_string($base) ? rtrim($base, '/') : '';
    }
}
