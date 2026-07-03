<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ClaySupplier;
use App\Support\PaginatesWithCache;
use App\Support\Pagination;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ClaySupplierService
{
    use PaginatesWithCache;
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): ClaySupplier
    {
        return ClaySupplier::create($attributes);
    }

    public function delete(ClaySupplier $supplier): void
    {
        $supplier->delete();
    }

    /**
     * @return LengthAwarePaginator<int, ClaySupplier>
     */
    public function paginate(int $perPage): LengthAwarePaginator
    {
        $query = ClaySupplier::query();

        return $this->cachedPaginate(ClaySupplier::class, $query, $perPage, orderBy: ['name' => 'asc']);
    }

    public function restore(ClaySupplier $supplier): ClaySupplier
    {
        $supplier->restore();

        return $supplier;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(array $attributes, ClaySupplier $supplier): ClaySupplier
    {
        $supplier->update($attributes);

        return $supplier;
    }
}
