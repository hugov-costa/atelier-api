<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Enrollment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Queued reminder that an unpaid annual enrollment fee is either approaching its
 * due date or already overdue. Delivered by email and stored in-app.
 */
class AnnualFeeDue extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private Enrollment $enrollment, private bool $overdue) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type'        => 'annual_fee_due',
            'message'     => $this->humanMessage(),
            'amount'      => $this->enrollment->annual_fee,
            'due_date'    => $this->enrollment->annual_fee_due_date?->toDateString(),
            'resource_id' => $this->enrollment->ulid,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->overdue ? 'Taxa de matrícula em atraso' : 'Taxa de matrícula a vencer')
            ->line($this->humanMessage())
            ->line(sprintf('Valor: R$ %s.', number_format($this->enrollment->annual_fee / 100, 2, ',', '.')))
            ->line(sprintf('Vencimento: %s.', $this->enrollment->annual_fee_due_date?->format('d/m/Y') ?? '-'))
            ->action('Ver matrícula', $this->frontendUrl());
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

    private function humanMessage(): string
    {
        return $this->overdue
            ? 'Sua taxa de matrícula anual está em atraso.'
            : 'Sua taxa de matrícula anual está próxima do vencimento.';
    }
}
