<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PieceKind;
use App\Models\Enrollment;
use App\Models\Piece;
use App\Models\PieceCharge;
use App\Models\Setting;
use App\Models\TuitionFee;
use App\Models\User;
use App\Notifications\PieceChargeCreated;
use App\Support\BillingCycle;
use App\Support\BusinessDate;
use App\Support\Pagination;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class PieceChargeService
{
    /**
     * When a student becomes inactive, pull every still-unbilled open charge forward
     * to the next tuition due date so it is collected before they leave, regardless
     * of the window. Charges already bound to a materialized tuition are collected
     * with that tuition and left untouched.
     */
    public function accelerateForUser(User $user): void
    {
        $nextDue = BillingCycle::nextDueDate(
            Setting::current()->tuition_fee_due_day_of_month,
            BusinessDate::now(),
        );

        PieceCharge::query()
            ->where('user_id', $user->id)
            ->whereNull('tuition_fee_id')
            ->whereNull('paid_at')
            ->where('due_date', '>', $nextDue)
            ->update(['due_date' => $nextDue]);
    }

    /**
     * Raise the receivable for a freshly created student piece. Commission pieces are
     * sold to customers, not billed to a student, so they never produce a charge. The
     * amount is snapshotted from the piece price and the due date follows the billing
     * cycle window.
     */
    public function createForPiece(Piece $piece): ?PieceCharge
    {
        if ($piece->kind !== PieceKind::Student) {
            return null;
        }

        if ($this->isExemptFromPieceCharges($piece->user_id)) {
            return null;
        }

        $settings = Setting::current();

        $dueDate = BillingCycle::pieceChargeDueDate(
            BusinessDate::now(),
            $settings->tuition_fee_due_day_of_month,
            $settings->piece_charge_billing_grace_days,
        );

        $charge = PieceCharge::create([
            'piece_id'       => $piece->id,
            'user_id'        => $piece->user_id,
            'tuition_fee_id' => $this->tuitionForCycle($dueDate, $piece->user_id)?->id,
            'amount'         => $piece->price,
            'due_date'       => $dueDate,
            'paid_at'        => null,
        ]);

        $user = User::query()->find($piece->user_id);
        $user?->notify(new PieceChargeCreated($charge));

        return $charge;
    }

    /**
     * Attach the student's still-unbilled piece charges for a cycle to the tuition
     * that has just materialized for it. Charges are created before their target
     * tuition exists, so this closes the loop and lets settlement follow the link
     * rather than a shared date. Only unpaid, unlinked charges are attached, so a
     * charge already settled by hand (e.g. a tuition-exempt student) is left alone.
     */
    public function linkChargesToFee(TuitionFee $tuitionFee): void
    {
        $tuitionFee->loadMissing('enrollment');

        PieceCharge::query()
            ->where('user_id', $tuitionFee->enrollment->user_id)
            ->whereNull('tuition_fee_id')
            ->whereNull('paid_at')
            ->where('due_date', $tuitionFee->due_date->toDateString())
            ->update(['tuition_fee_id' => $tuitionFee->id]);
    }

    /**
     * Manually mark a single charge paid or unpaid. This is the staff override for
     * charges that cannot settle through a tuition — e.g. a tuition-exempt student
     * who is still billed for their pieces — and for corrections.
     */
    public function markPaid(PieceCharge $charge, bool $isPaid): PieceCharge
    {
        if ($isPaid && $charge->paid_at === null) {
            $charge->paid_at = Carbon::now();
            $charge->save();
        } elseif (! $isPaid && $charge->paid_at !== null) {
            $charge->paid_at = null;
            $charge->save();
        }

        return $charge->load(['piece', 'user']);
    }

    /**
     * @param  array{status?: string|null, user_id?: string|null}  $filters
     * @return LengthAwarePaginator<int, PieceCharge>
     */
    public function paginate(int $perPage, array $filters = []): LengthAwarePaginator
    {
        $status = $filters['status'] ?? null;
        $userUlid = $filters['user_id'] ?? null;

        return PieceCharge::query()->with(['piece', 'user'])
            ->when($status === 'paid', fn (Builder $query): Builder => $query->whereNotNull('paid_at'))
            ->when($status === 'unpaid', fn (Builder $query): Builder => $query->whereNull('paid_at'))
            ->when(is_string($userUlid) && $userUlid !== '', fn (Builder $query): Builder => $query->whereHas(
                'user',
                fn (Builder $userQuery): Builder => $userQuery->where('ulid', $userUlid)
            ))
            ->orderBy('due_date')
            ->paginate(Pagination::perPage($perPage));
    }

    /**
     * Settle the piece charges billed on a tuition when that tuition is paid: they
     * share the tuition's payment moment.
     */
    public function settleForTuition(TuitionFee $tuitionFee): void
    {
        if ($tuitionFee->paid_at === null) {
            return;
        }

        $this->linkChargesToFee($tuitionFee);

        PieceCharge::query()
            ->where('tuition_fee_id', $tuitionFee->id)
            ->whereNull('paid_at')
            ->update(['paid_at' => $tuitionFee->paid_at]);
    }

    /**
     * Reverse the settlement when a tuition is marked unpaid again. Only charges
     * bound to this tuition are touched, so a hand-settled charge that never
     * belonged to it stays paid.
     */
    public function unsettleForTuition(TuitionFee $tuitionFee): void
    {
        PieceCharge::query()
            ->where('tuition_fee_id', $tuitionFee->id)
            ->whereNotNull('paid_at')
            ->update(['paid_at' => null]);
    }

    /**
     * The student's most recent enrollment, used to resolve exemption deterministically
     * when a user has more than one enrollment over time.
     */
    private function currentEnrollment(int $userId): ?Enrollment
    {
        return Enrollment::query()
            ->where('user_id', $userId)
            ->orderByDesc('id')
            ->first();
    }

    private function isExemptFromPieceCharges(int $userId): bool
    {
        return (bool) $this->currentEnrollment($userId)?->is_exempt_from_piece_charges;
    }

    /**
     * The unpaid tuition already raised for the student on the given cycle date, if any.
     */
    private function tuitionForCycle(string $dueDate, int $userId): ?TuitionFee
    {
        return TuitionFee::query()
            ->whereNull('paid_at')
            ->where('due_date', $dueDate)
            ->whereHas('enrollment', fn (Builder $query): Builder => $query->where('user_id', $userId))
            ->first();
    }
}
