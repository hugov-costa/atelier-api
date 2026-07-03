<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\FiringCycle;
use App\Support\PaginatesWithCache;
use App\Support\Pagination;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class FiringCycleService
{
    use PaginatesWithCache;
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): FiringCycle
    {
        return FiringCycle::create($attributes);
    }

    public function delete(FiringCycle $cycle): void
    {
        $cycle->delete();
    }

    /**
     * @return LengthAwarePaginator<int, FiringCycle>
     */
    public function paginate(int $perPage): LengthAwarePaginator
    {
        $query = FiringCycle::query();

        return $this->cachedPaginate(FiringCycle::class, $query, $perPage, orderBy: ['cycle' => 'asc', 'name' => 'asc']);
    }

    public function restore(FiringCycle $cycle): FiringCycle
    {
        $cycle->restore();

        return $cycle;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(array $attributes, FiringCycle $cycle): FiringCycle
    {
        $cycle->update($attributes);

        return $cycle;
    }
}
