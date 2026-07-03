<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Cache\TaggableStore;
use Illuminate\Cache\TaggedCache;
use Illuminate\Support\Facades\Cache;

/**
 * Helpers around the tag-based read-query cache. Caching is only attempted when
 * it is enabled in config and the active cache store supports tags, so a deploy
 * pointed at a non-taggable store (database, file) degrades to uncached reads
 * instead of throwing on every request.
 */
final class QueryCache
{
    public static function enabled(): bool
    {
        return (bool) config('cache.query.enabled', true) && self::supportsTags();
    }

    public static function supportsTags(): bool
    {
        return Cache::getStore() instanceof TaggableStore;
    }

    /**
     * @param  array<int, string>  $tags
     */
    public static function tagged(array $tags): TaggedCache
    {
        return Cache::tags($tags);
    }

    /**
     * @param  array<int, string>  $tags
     */
    public static function flush(array $tags): void
    {
        if (self::enabled()) {
            Cache::tags($tags)->flush();
        }
    }
}
