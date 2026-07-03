<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\PieceCharge;
use App\Models\TuitionFee;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * @phpstan-type HistoryEntry array{
 *     type: string,
 *     amount: int,
 *     paid_at: string,
 * }
 */
class StudentStatementService
{
    /**
     * A consolidated financial statement for a single student. All monetary values are in cents.
     *
     * @return array<string, mixed>
     */
    public function build(User $student): array
    {
        $today = Carbon::today();

        $tuitionItems = [];
        $annualFeeItems = [];
        $pieceChargeItems = [];

        /** @var list<HistoryEntry> $history */
        $history = [];

        $tuitionFees = TuitionFee::query()
            ->whereHas('enrollment', fn ($query) => $query->where('user_id', $student->id))
            ->with('enrollment')
            ->get();

        foreach ($tuitionFees as $fee) {
            $dueDate = $fee->due_date;
            $paidAt = $fee->paid_at;
            $status = $this->status($dueDate, $paidAt !== null, $today);

            $tuitionItems[] = [
                'id'       => $fee->ulid,
                'kind'     => 'tuition',
                'amount'   => $fee->amount,
                'due_date' => $dueDate->toDateString(),
                'paid_at'  => $paidAt?->toIso8601String(),
                'status'   => $status,
            ];

            if ($paidAt !== null) {
                $history[] = [
                    'type'    => 'tuition',
                    'amount'  => $fee->amount,
                    'paid_at' => $paidAt->toIso8601String(),
                ];
            }
        }

        $enrollments = $student->enrollments()->get();

        foreach ($enrollments as $enrollment) {
            if ($enrollment->annual_fee <= 0) {
                continue;
            }

            $dueDate = $enrollment->annual_fee_due_date;
            $paidAt = $enrollment->annual_fee_paid_at;
            $isPaid = $enrollment->annual_fee_is_paid;
            $status = $this->status($dueDate, $isPaid, $today);

            $annualFeeItems[] = [
                'id'       => $enrollment->ulid,
                'kind'     => 'annual_fee',
                'amount'   => $enrollment->annual_fee,
                'due_date' => $dueDate?->toDateString(),
                'paid_at'  => $paidAt?->toIso8601String(),
                'status'   => $status,
            ];

            if ($isPaid && $paidAt !== null) {
                $history[] = [
                    'type'    => 'annual_fee',
                    'amount'  => $enrollment->annual_fee,
                    'paid_at' => $paidAt->toIso8601String(),
                ];
            }
        }

        $pieceCharges = PieceCharge::query()
            ->where('user_id', $student->id)
            ->with('piece')
            ->get();

        foreach ($pieceCharges as $charge) {
            $dueDate = $charge->due_date;
            $paidAt = $charge->paid_at;
            $status = $this->status($dueDate, $paidAt !== null, $today);

            $pieceChargeItems[] = [
                'id'       => $charge->ulid,
                'amount'   => $charge->amount,
                'due_date' => $dueDate->toDateString(),
                'paid_at'  => $paidAt?->toIso8601String(),
                'status'   => $status,
                'piece'    => [
                    'name' => $charge->relationLoaded('piece') && $charge->piece !== null
                        ? $charge->piece->name
                        : null,
                ],
            ];

            if ($paidAt !== null) {
                $history[] = [
                    'type'    => 'piece_charge',
                    'amount'  => $charge->amount,
                    'paid_at' => $paidAt->toIso8601String(),
                ];
            }
        }

        $balance = $this->balance($annualFeeItems, $pieceChargeItems, $tuitionItems);

        usort($history, fn (array $a, array $b): int => strcmp($b['paid_at'], $a['paid_at']));
        $history = array_slice($history, 0, 20);

        return [
            'student' => [
                'id'   => $student->ulid,
                'name' => $student->name,
            ],
            'balance' => $balance,
            'items'   => [
                'tuition'       => $tuitionItems,
                'annual_fees'   => $annualFeeItems,
                'piece_charges' => $pieceChargeItems,
            ],
            'history' => array_values($history),
        ];
    }

    /**
     * Sum unpaid amounts into total/overdue/upcoming buckets.
     *
     * @param  list<array<string, mixed>>  $annualFeeItems
     * @param  list<array<string, mixed>>  $pieceChargeItems
     * @param  list<array<string, mixed>>  $tuitionItems
     * @return array{total_outstanding: int, overdue: int, upcoming: int}
     */
    private function balance(array $annualFeeItems, array $pieceChargeItems, array $tuitionItems): array
    {
        $total = 0;
        $overdue = 0;
        $upcoming = 0;

        foreach ([$tuitionItems, $annualFeeItems, $pieceChargeItems] as $group) {
            foreach ($group as $item) {
                if ($item['status'] === 'paid') {
                    continue;
                }

                $amount = is_int($item['amount']) ? $item['amount'] : 0;
                $total += $amount;

                if ($item['status'] === 'overdue') {
                    $overdue += $amount;
                } else {
                    $upcoming += $amount;
                }
            }
        }

        return [
            'total_outstanding' => $total,
            'overdue'           => $overdue,
            'upcoming'          => $upcoming,
        ];
    }

    /**
     * Derive an item status from its paid flag and due date.
     */
    private function status(?Carbon $dueDate, bool $isPaid, Carbon $today): string
    {
        if ($isPaid) {
            return 'paid';
        }

        if ($dueDate !== null && $dueDate->lt($today)) {
            return 'overdue';
        }

        return 'open';
    }
}
