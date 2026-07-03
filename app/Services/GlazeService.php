<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Glaze;
use App\Models\GlazeSupplier;
use App\Support\PaginatesWithCache;
use App\Support\Pagination;
use App\Support\PublicId;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class GlazeService
{
    use PaginatesWithCache;
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Glaze
    {
        return Glaze::create($this->resolveSupplier($attributes));
    }

    public function delete(Glaze $glaze): void
    {
        $glaze->delete();
    }

    /**
     * @return LengthAwarePaginator<int, Glaze>
     */
    public function paginate(int $perPage): LengthAwarePaginator
    {
        $query = Glaze::query()->with('glazeSupplier');

        return $this->cachedPaginate(Glaze::class, $query, $perPage, orderBy: ['name' => 'asc']);
    }

    public function restore(Glaze $glaze): Glaze
    {
        $glaze->restore();

        return $glaze->load('glazeSupplier');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(array $attributes, Glaze $glaze): Glaze
    {
        $glaze->update($this->resolveSupplier($attributes));

        return $glaze->load('glazeSupplier');
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function resolveSupplier(array $attributes): array
    {
        if (isset($attributes['glaze_supplier_id']) && is_string($attributes['glaze_supplier_id'])) {
            $attributes['glaze_supplier_id'] = PublicId::resolve(
                GlazeSupplier::class,
                $attributes['glaze_supplier_id'],
            );
        }

        return $attributes;
    }
}
