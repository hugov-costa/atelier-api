<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Support\QueryCache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Flushes the model's query-cache tag when a row changes in a way that affects
 * cached read queries, so cached reads are rebuilt on the next request without
 * being needlessly invalidated by high-frequency, non-display writes (such as a
 * login touch or a rotating two-factor timestep).
 */
trait InvalidatesQueryCache
{
    public static function bootInvalidatesQueryCache(): void
    {
        $tag = static::cacheTag();

        $flush = static function () use ($tag): void {
            QueryCache::flush([$tag]);
        };

        static::registerModelEvent('saved', static function (Model $model) use ($flush): void {
            if (static::changeInvalidatesQueryCache($model)) {
                $flush();
            }
        });

        foreach (['deleted', 'restored', 'forceDeleted'] as $event) {
            static::registerModelEvent($event, $flush);
        }
    }

    protected static function changeInvalidatesQueryCache(Model $model): bool
    {
        if ($model->wasRecentlyCreated) {
            return true;
        }

        $columns = static::queryCacheColumns();

        return $columns === null || $model->wasChanged($columns);
    }

    /**
     * Columns whose change invalidates cached read queries. Returning null keeps
     * the conservative default of invalidating on any change.
     *
     * @return array<int, string>|null
     */
    protected static function queryCacheColumns(): ?array
    {
        return null;
    }

    public static function cacheTag(): string
    {
        return 'query:'.Str::snake(class_basename(static::class));
    }
}
