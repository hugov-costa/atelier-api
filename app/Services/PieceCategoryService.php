<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\PieceCategory;
use App\Support\PaginatesWithCache;
use App\Support\Pagination;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PieceCategoryService
{
    use PaginatesWithCache;
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): PieceCategory
    {
        return PieceCategory::create($attributes);
    }

    public function delete(PieceCategory $category): void
    {
        $category->delete();
    }

    /**
     * @return LengthAwarePaginator<int, PieceCategory>
     */
    public function paginate(int $perPage): LengthAwarePaginator
    {
        $query = PieceCategory::query();

        return $this->cachedPaginate(PieceCategory::class, $query, $perPage, orderBy: ['name' => 'asc']);
    }

    public function restore(PieceCategory $category): PieceCategory
    {
        $category->restore();

        return $category;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(array $attributes, PieceCategory $category): PieceCategory
    {
        $category->update($attributes);

        return $category;
    }
}
