<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\PieceCharge;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Notification;

/**
 * Queued in-app notice that a charge was raised for a student piece. Stored in-app
 * only (no email). It is raised inside the piece-creation transaction, so it must
 * dispatch only after that transaction commits (the queue has after_commit off).
 */
class PieceChargeCreated extends Notification implements ShouldQueue, ShouldQueueAfterCommit
{
    use Queueable;

    public function __construct(private PieceCharge $charge) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type'        => 'piece_charge_created',
            'message'     => 'Uma cobrança de peça foi gerada para você.',
            'amount'      => $this->charge->amount,
            'due_date'    => $this->charge->due_date->toDateString(),
            'resource_id' => $this->charge->ulid,
        ];
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }
}
