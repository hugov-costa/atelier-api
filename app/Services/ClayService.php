<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Clay;
use App\Models\ClaySupplier;
use App\Support\PaginatesWithCache;
use App\Support\Pagination;
use App\Support\PublicId;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ClayService
{
    use PaginatesWithCache;

    /**
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Clay
    {
        return Clay::create($this->resolveSupplier($attributes));
    }

    public function delete(Clay $clay): void
    {
        $clay->delete();
    }

    /**
     * @return LengthAwarePaginator<int, Clay>
     */
    public function paginate(int $perPage): LengthAwarePaginator
    {
        $query = Clay::query()->with('claySupplier');

        return $this->cachedPaginate(Clay::class, $query, $perPage, orderBy: ['name' => 'asc']);
    }

    public function restore(Clay $clay): Clay
    {
        $clay->restore();

        return $clay->load('claySupplier');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(array $attributes, Clay $clay): Clay
    {
        $clay->update($this->resolveSupplier($attributes));

        return $clay->load('claySupplier');
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function resolveSupplier(array $attributes): array
    {
        if (isset($attributes['clay_supplier_id']) && is_string($attributes['clay_supplier_id'])) {
            $attributes['clay_supplier_id'] = PublicId::resolve(ClaySupplier::class, $attributes['clay_supplier_id']);
        }

        return $attributes;
    }
}
