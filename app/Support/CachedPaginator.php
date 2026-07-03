<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;

/**
 * Converts a LengthAwarePaginator to a cache-safe array (no objects) and back.
 *
 * Keeps `serializable_classes => false` secure by never storing Eloquent models
 * or framework objects in the cache — only plain arrays and scalars.
 *
 * Usage:
 * <code>
 * // Before caching:
 * $cached = CachedPaginator::toArray($paginator);
 *
 * // After cache hit:
 * $paginator = CachedPaginator::fromArray($cached, Model::query()->with('relation'));
 * </code>
 */
final class CachedPaginator
{
    /**
     * @template TModel of Model
     *
     * @param  LengthAwarePaginator<int, TModel>  $paginator
     * @return array{item_ids: array<int, int>, meta: array{total: int, per_page: int, current_page: int, last_page: int, path: string}}
     */
    public static function toArray(LengthAwarePaginator $paginator): array
    {
        $items = $paginator->items();

        if ($items instanceof \Illuminate\Support\Collection) {
            /** @var array<int, int> $ids */
            $ids = $items->modelKeys();
        } else {
            /** @var array<int, int> $ids */
            $ids = array_values(array_map(function (mixed $m): int {
                /** @var int $key */
                $key = $m instanceof Model ? $m->getKey() : 0;

                return $key;
            }, $items));
        }

        return [
            'item_ids' => $ids,
            'meta'     => [
                'total'        => $paginator->total(),
                'per_page'     => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'path'         => $paginator->path() ?? '',
            ],
        ];
    }

    /**
     * Reconstruct a LengthAwarePaginator from cached data.
     *
     * @template TModel of Model
     *
     * @param  array{item_ids: array<int, int>, meta: array{total: int, per_page: int, current_page: int, last_page: int, path: string}}  $data
     * @param  Builder<TModel>  $query
     * @return LengthAwarePaginator<int, Model>
     */
    public static function fromArray(array $data, Builder $query): LengthAwarePaginator
    {
        $keyName = $query->getModel()->getQualifiedKeyName();

        /** @var Collection<int, TModel> $models */
        $models = $query->whereIn($keyName, $data['item_ids'])->get();

        $sorted = $models->sortBy(
            fn (Model $model): int|false => array_search($model->getKey(), $data['item_ids'], true),
        )->values();

        return new Paginator(
            $sorted,
            $data['meta']['total'],
            $data['meta']['per_page'],
            $data['meta']['current_page'],
            ['path' => $data['meta']['path']],
        );
    }
}
