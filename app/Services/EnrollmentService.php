<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Enrollment;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\EnrollmentCreated;
use App\Support\BusinessDate;
use App\Support\Pagination;
use App\Support\PublicId;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class EnrollmentService
{
    /**
     * The annual fee is never client-supplied: it is derived from the current
     * settings unless the student is exempt.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Enrollment
    {
        $attributes['user_id'] = PublicId::resolve(User::class, $attributes['user_id'] ?? null);
        $attributes['annual_fee'] = $this->annualFeeOnCreate($attributes);
        $attributes['annual_fee_due_date'] ??= BusinessDate::today();

        $enrollment = Enrollment::create($attributes);
        $this->syncAnnualFeePaidAt($enrollment);

        $enrollment->load('user');
        $enrollment->user->notify(new EnrollmentCreated($enrollment));

        return $enrollment;
    }

    public function delete(Enrollment $enrollment): void
    {
        $enrollment->delete();
    }

    /**
     * @param  array{annual_fee_is_paid?: bool|null, user_id?: string|null}  $filters
     * @return LengthAwarePaginator<int, Enrollment>
     */
    public function paginate(int $perPage, array $filters = []): LengthAwarePaginator
    {
        $paid = $filters['annual_fee_is_paid'] ?? null;
        $userUlid = $filters['user_id'] ?? null;

        return Enrollment::query()->with('user')
            ->when($paid !== null, fn (Builder $query): Builder => $query->where('annual_fee_is_paid', $paid))
            ->when(is_string($userUlid) && $userUlid !== '', fn (Builder $query): Builder => $query->whereHas(
                'user',
                fn (Builder $userQuery): Builder => $userQuery->where('ulid', $userUlid)
            ))
            ->orderByDesc('created_at')
            ->paginate(Pagination::perPage($perPage));
    }

    public function restore(Enrollment $enrollment): Enrollment
    {
        $enrollment->restore();

        return $enrollment->load('user');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(array $attributes, Enrollment $enrollment): Enrollment
    {
        $attributes['annual_fee'] = $this->annualFeeOnUpdate($attributes, $enrollment);

        $enrollment->update($attributes);
        $this->syncAnnualFeePaidAt($enrollment);

        return $enrollment->load('user');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function annualFeeOnCreate(array $attributes): int
    {
        $exempt = (bool) ($attributes['is_exempt_from_annual_fee'] ?? false);

        return $exempt ? 0 : Setting::current()->annual_enrollment_cost;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function annualFeeOnUpdate(array $attributes, Enrollment $enrollment): int
    {
        $exempt = (bool) ($attributes['is_exempt_from_annual_fee'] ?? $enrollment->is_exempt_from_annual_fee);

        if ($enrollment->annual_fee === 0 && ! $exempt) {
            return Setting::current()->annual_enrollment_cost;
        }

        if ($enrollment->annual_fee > 0 && $exempt) {
            return 0;
        }

        return $enrollment->annual_fee;
    }

    /**
     * Keep the annual-fee payment timestamp in step with the paid flag so the
     * monthly report can attribute the revenue to the month it was received.
     */
    private function syncAnnualFeePaidAt(Enrollment $enrollment): void
    {
        if ($enrollment->annual_fee_is_paid && $enrollment->annual_fee_paid_at === null) {
            $enrollment->annual_fee_paid_at = Carbon::now();
            $enrollment->save();
        } elseif (! $enrollment->annual_fee_is_paid && $enrollment->annual_fee_paid_at !== null) {
            $enrollment->annual_fee_paid_at = null;
            $enrollment->save();
        }
    }
}
