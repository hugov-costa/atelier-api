<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Resolves a public ULID back to the internal auto-increment key.
 *
 * Throws ModelNotFoundException when no row matches the given ULID, so
 * callers get a clean 404 instead of a silent 0 that could cause a FK
 * violation downstream.
 */
final class PublicId
{
    /**
     * @template TModel of Model
     *
     * @param  class-string<TModel>  $model
     * @return int<0, max>
     *
     * @throws ModelNotFoundException
     */
    public static function resolve(string $model, mixed $ulid): int
    {
        if (! is_string($ulid)) {
            throw (new ModelNotFoundException)->setModel($model);
        }

        /** @var TModel $instance */
        $instance = $model::query()->where('ulid', $ulid)->firstOrFail();

        /** @var int<0, max> */
        return $instance->getKey();
    }

    /**
     * Map a list of public ULIDs to their internal keys.
     *
     * @template TModel of Model
     *
     * @param  class-string<TModel>  $model
     * @param  array<int, string>  $ulids
     * @return array<int, int>
     */
    public static function resolveMany(string $model, array $ulids): array
    {
        if ($ulids === []) {
            return [];
        }

        /** @var array<int, int> $ids */
        $ids = $model::query()->whereIn('ulid', $ulids)->pluck('id')->all();

        return $ids;
    }
}
