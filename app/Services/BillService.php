<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Bill;
use App\Support\BillingCycle;
use App\Support\Pagination;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class BillService
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Bill
    {
        return Bill::create($attributes);
    }

    public function delete(Bill $bill): void
    {
        $bill->delete();
    }

    /**
     * Carry recurrent bills into the given month: for each recurrent obligation
     * (identified by the latest recurrent bill of each name), create a bill for the
     * anchor month unless one already exists. Idempotent, and safe to re-run: a
     * soft-deleted bill counts as "exists", so a deleted obligation is never silently
     * recreated. Returns the number of bills created.
     */
    public function generateRecurrentForMonth(Carbon $anchor): int
    {
        /** @var array<int, int> $templateIds */
        $templateIds = Bill::query()
            ->where('is_recurrent', true)
            ->selectRaw('MAX(id) as id')
            ->groupBy('name')
            ->pluck('id')
            ->all();

        $templates = Bill::query()->whereIn('id', $templateIds)->get();

        $existingNames = Bill::withTrashed()
            ->where('reference_year', $anchor->year)
            ->where('reference_month', $anchor->month)
            ->pluck('name')
            ->flip();

        $created = 0;

        foreach ($templates as $template) {
            if ($existingNames->has($template->name)) {
                continue;
            }

            Bill::create([
                'name'            => $template->name,
                'description'     => $template->description,
                'value'           => $template->value,
                'is_recurrent'    => true,
                'reference_year'  => $anchor->year,
                'reference_month' => $anchor->month,
                'due_date'        => BillingCycle::dueDateForMonth($anchor, $template->due_date->day),
            ]);

            $created++;
        }

        return $created;
    }

    /**
     * @param  array{reference_year?: int|null, reference_month?: int|null, is_recurrent?: bool|null}  $filters
     * @return LengthAwarePaginator<int, Bill>
     */
    public function paginate(int $perPage, array $filters = []): LengthAwarePaginator
    {
        $year = $filters['reference_year'] ?? null;
        $month = $filters['reference_month'] ?? null;
        $isRecurrent = $filters['is_recurrent'] ?? null;

        return Bill::query()
            ->when($year !== null, fn (Builder $query): Builder => $query->where('reference_year', $year))
            ->when($month !== null, fn (Builder $query): Builder => $query->where('reference_month', $month))
            ->when($isRecurrent !== null, fn (Builder $query): Builder => $query->where('is_recurrent', $isRecurrent))
            ->orderByDesc('reference_year')
            ->orderByDesc('reference_month')
            ->orderByDesc('due_date')
            ->paginate(Pagination::perPage($perPage));
    }

    public function restore(Bill $bill): Bill
    {
        $bill->restore();

        return $bill;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(array $attributes, Bill $bill): Bill
    {
        $bill->update($attributes);

        return $bill;
    }
}
