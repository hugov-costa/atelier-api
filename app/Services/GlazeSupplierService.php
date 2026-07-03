<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\GlazeSupplier;
use App\Support\PaginatesWithCache;
use App\Support\Pagination;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class GlazeSupplierService
{
    use PaginatesWithCache;
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): GlazeSupplier
    {
        return GlazeSupplier::create($attributes);
    }

    public function delete(GlazeSupplier $supplier): void
    {
        $supplier->delete();
    }

    /**
     * @return LengthAwarePaginator<int, GlazeSupplier>
     */
    public function paginate(int $perPage): LengthAwarePaginator
    {
        $query = GlazeSupplier::query();

        return $this->cachedPaginate(GlazeSupplier::class, $query, $perPage, orderBy: ['name' => 'asc']);
    }

    public function restore(GlazeSupplier $supplier): GlazeSupplier
    {
        $supplier->restore();

        return $supplier;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(array $attributes, GlazeSupplier $supplier): GlazeSupplier
    {
        $supplier->update($attributes);

        return $supplier;
    }
}
