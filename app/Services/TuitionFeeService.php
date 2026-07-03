<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Enrollment;
use App\Models\Setting;
use App\Models\TuitionFee;
use App\Support\BillingCycle;
use App\Support\BusinessDate;
use App\Support\Pagination;
use App\Support\PublicId;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class TuitionFeeService
{
    public function __construct(private PieceChargeService $charges, private StatementService $statements) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): TuitionFee
    {
        $enrollmentId = PublicId::resolve(Enrollment::class, $attributes['enrollment_id'] ?? null);
        $enrollment = Enrollment::query()->findOrFail($enrollmentId);

        $tuitionFee = DB::transaction(function () use ($enrollmentId, $enrollment): TuitionFee {
            $tuitionFee = new TuitionFee([
                'enrollment_id' => $enrollmentId,
                'amount'        => $enrollment->is_exempt_from_tuition_fee
                    ? 0
                    : Setting::current()->tuition_monthly_cost,
                'due_date' => $this->nextDueDate(),
            ]);

            if ($enrollment->is_exempt_from_tuition_fee) {
                $tuitionFee->paid_at = Carbon::now();
            }

            $tuitionFee->save();

            $this->charges->linkChargesToFee($tuitionFee);

            return $tuitionFee;
        });

        $tuitionFee->load('enrollment.user');
        $this->statements->sendForUserCycle(
            'generated',
            $tuitionFee->due_date->toDateString(),
            $tuitionFee->enrollment->user,
        );

        return $tuitionFee->load('enrollment');
    }

    public function delete(TuitionFee $tuitionFee): void
    {
        $tuitionFee->delete();
    }

    /**
     * Create the given month's tuition fee for every active, non-exempt
     * enrollment that does not already have one for that month. Idempotent, so a
     * missed run is safely re-attempted. Returns the number of fees created.
     */
    public function generateForMonth(Carbon $anchor): int
    {
        $dueDate = $this->dueDateForMonth($anchor);
        $amount = Setting::current()->tuition_monthly_cost;
        $created = 0;

        $monthStart = $anchor->copy()->startOfMonth()->toDateString();
        $nextMonthStart = $anchor->copy()->startOfMonth()->addMonthNoOverflow()->toDateString();

        Enrollment::query()
            ->with('user')
            ->where('is_exempt_from_tuition_fee', false)
            ->whereHas('user', fn (Builder $query): Builder => $query->where('is_active', true))
            ->whereDoesntHave('tuitionFees', fn (Builder $query): Builder => $query
                ->where('due_date', '>=', $monthStart)
                ->where('due_date', '<', $nextMonthStart))
            ->chunkById(200, function (Collection $enrollments) use ($dueDate, $amount, &$created): void {
                foreach ($enrollments as $enrollment) {
                    $fee = TuitionFee::create([
                        'enrollment_id' => $enrollment->id,
                        'amount'        => $amount,
                        'due_date'      => $dueDate,
                    ]);
                    $fee->setRelation('enrollment', $enrollment);
                    $this->charges->linkChargesToFee($fee);
                    $created++;
                }
            });

        $this->statements->sendForCycle('generated', Carbon::parse($dueDate));

        return $created;
    }

    /**
     * @param  array{status?: string|null}  $filters
     * @return LengthAwarePaginator<int, TuitionFee>
     */
    public function paginate(int $perPage, array $filters = []): LengthAwarePaginator
    {
        $status = $filters['status'] ?? null;

        return TuitionFee::query()->with('enrollment')
            ->when($status === 'paid', fn (Builder $query): Builder => $query->whereNotNull('paid_at'))
            ->when($status === 'unpaid', fn (Builder $query): Builder => $query->whereNull('paid_at'))
            ->orderByDesc('due_date')
            ->paginate(Pagination::perPage($perPage));
    }

    public function restore(TuitionFee $tuitionFee): TuitionFee
    {
        $tuitionFee->restore();

        return $tuitionFee->load('enrollment');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(array $attributes, TuitionFee $tuitionFee): TuitionFee
    {
        $isPaid = (bool) ($attributes['is_paid'] ?? false);

        return DB::transaction(function () use ($tuitionFee, $isPaid): TuitionFee {
            if ($isPaid && $tuitionFee->paid_at === null) {
                $tuitionFee->paid_at = Carbon::now();
                $tuitionFee->save();
                $this->charges->settleForTuition($tuitionFee);
            } elseif (! $isPaid && $tuitionFee->paid_at !== null) {
                $tuitionFee->paid_at = null;
                $tuitionFee->save();
                $this->charges->unsettleForTuition($tuitionFee);
            }

            return $tuitionFee->load('enrollment');
        });
    }

    /**
     * The due date within the anchor's month: the configured day-of-month,
     * clamped to the month length.
     */
    private function dueDateForMonth(Carbon $anchor): string
    {
        return BillingCycle::dueDateForMonth($anchor, Setting::current()->tuition_fee_due_day_of_month);
    }

    /**
     * Due next month (used by the manual create endpoint).
     */
    private function nextDueDate(): string
    {
        return $this->dueDateForMonth(BusinessDate::now()->addMonthNoOverflow());
    }
}
