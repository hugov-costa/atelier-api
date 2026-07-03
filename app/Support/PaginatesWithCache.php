<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Str;

/**
 * Adds cache-aside pagination to a service.
 *
 * Usage in a Service:
 * <code>
 * use App\Support\PaginatesWithCache;
 *
 * class ResourceService
 * {
 *     use PaginatesWithCache;
 *
 *     public function paginate(int $perPage): LengthAwarePaginator
 *     {
 *         $query = Resource::query()->with('relation');
 *
 *         return $this->cachedPaginate(Resource::class, $query, $perPage, orderBy: ['name' => 'asc']);
 *     }
 * }
 * </code>
 *
 * The model class MUST use the InvalidatesQueryCache trait for cache invalidation
 * to work correctly.
 */
trait PaginatesWithCache
{
    /**
     * Execute a paginated query with cache-aside (Redis + tag-based invalidation).
     *
     * @template TModel of Model
     *
     * @param  class-string<TModel>  $modelClass  Must use InvalidatesQueryCache trait
     * @param  Builder<TModel>  $query  Pre-configured with eager-loads and filters
     * @param  array<string, 'asc'|'desc'>  $orderBy  [column => direction]
     */
    public function cachedPaginate(
        string $modelClass,
        Builder $query,
        int $perPage,
        array $orderBy = ['created_at' => 'desc'],
    ): LengthAwarePaginator {
        $perPage = Pagination::perPage($perPage);

        if (! QueryCache::enabled()) {
            return $this->runPaginate($query, $perPage, $orderBy);
        }

        $key = sprintf(
            '%s:pp%d:p%d',
            (new $modelClass)->getTable(),
            $perPage,
            Paginator::resolveCurrentPage(),
        );

        $tag = 'query:'.Str::snake(class_basename($modelClass));
        $store = QueryCache::tagged([$tag]);
        $ttl = $this->resolveCacheTtl();

        $cached = $store->get($key);

        if (is_array($cached) && isset($cached['item_ids'], $cached['meta'])) {
            /** @var array{item_ids: array<int, int>, meta: array{total: int, per_page: int, current_page: int, last_page: int, path: string}} $validated */
            $validated = $cached;

            /** @phpstan-ignore return.type */
            return CachedPaginator::fromArray($validated, $query->clone());
        }

        $paginator = $this->runPaginate($query, $perPage, $orderBy);
        $store->put($key, CachedPaginator::toArray($paginator), $ttl);

        /** @phpstan-ignore return.type */
        return $paginator;
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  array<string, 'asc'|'desc'>  $orderBy
     */
    private function runPaginate(Builder $query, int $perPage, array $orderBy): LengthAwarePaginator
    {
        foreach ($orderBy as $column => $direction) {
            $query->orderBy($column, $direction);
        }

        /** @phpstan-ignore return.type */
        return $query->paginate($perPage);
    }

    private function resolveCacheTtl(): int
    {
        $ttl = config('cache.query.ttl', 3600);

        return is_numeric($ttl) && (int) $ttl > 0 ? (int) $ttl : 3600;
    }
}
