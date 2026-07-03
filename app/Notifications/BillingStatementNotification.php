<?php

declare(strict_types=1);

namespace App\Notifications;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Queued consolidated cycle statement (tuition + itemized student pieces),
 * carrying a PDF invoice. Delivered by email and stored in-app.
 *
 * Dispatched only after the surrounding transaction commits, so a queue worker
 * never renders a statement from tuition/charge state that was rolled back.
 *
 * @phpstan-import-type Statement from \App\Services\StatementService
 */
class BillingStatementNotification extends Notification implements ShouldQueueAfterCommit
{
    use Queueable;

    /**
     * @param  Statement  $statement
     * @param  'generated'|'upcoming'|'overdue'  $context
     */
    public function __construct(private array $statement, private string $context) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type'            => 'billing_statement',
            'context'         => $this->context,
            'message'         => sprintf('Sua cobrança de %s está disponível.', $this->statement['reference_month']),
            'total'           => $this->statement['total'],
            'due_date'        => $this->statement['due_date'],
            'reference_month' => $this->statement['reference_month'],
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $pdf = Pdf::loadView('invoices.statement', ['s' => $this->statement]);

        $fileName = 'cobranca-'.Str::slug($this->statement['reference_month']).'.pdf';

        return (new MailMessage)
            ->subject($this->subject())
            ->greeting(sprintf('Olá, %s!', $this->statement['student_name']))
            ->line('Esta é a cobrança do atelier referente a '.$this->statement['reference_month'].'.')
            ->line($this->contextLine())
            ->line(sprintf('Valor total: R$ %s.', $this->formatCents($this->statement['total'])))
            ->line(sprintf('Vencimento: %s.', $this->formatDate($this->statement['due_date'])))
            ->line('O detalhamento completo está no PDF em anexo.')
            ->attachData($pdf->output(), $fileName, ['mime' => 'application/pdf']);
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    private function contextLine(): string
    {
        return match ($this->context) {
            'upcoming' => 'Este é um lembrete de que a cobrança vence em breve.',
            'overdue'  => 'Identificamos que esta cobrança está em atraso.',
            default    => 'Segue o resumo dos valores do mês.',
        };
    }

    private function formatCents(int $cents): string
    {
        return number_format($cents / 100, 2, ',', '.');
    }

    private function formatDate(string $date): string
    {
        return Carbon::parse($date)->format('d/m/Y');
    }

    private function subject(): string
    {
        $month = $this->statement['reference_month'];

        return match ($this->context) {
            'upcoming' => sprintf('Lembrete: cobrança de %s vence em breve', $month),
            'overdue'  => sprintf('Cobrança de %s em atraso', $month),
            default    => sprintf('Sua cobrança de %s', $month),
        };
    }
}
