<?php

declare(strict_types=1);

namespace App\Casts;

use DateTimeInterface;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Cast a pure calendar date, stored without a time component (`Y-m-d`) on every
 * driver. Postgres `DATE` columns already drop the time, but SQLite keeps whatever
 * string it is given; normalizing storage lets `where('col', '2026-02-10')` match
 * and use the column index on both, instead of the non-sargable `whereDate()`.
 *
 * @implements CastsAttributes<Carbon, Carbon|DateTimeInterface|string|int>
 */
class DateOnly implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Carbon
    {
        return match (true) {
            $value === null                     => null,
            $value instanceof DateTimeInterface => Carbon::instance($value)->startOfDay(),
            is_int($value)                      => Carbon::createFromTimestamp($value)->startOfDay(),
            is_string($value)                   => Carbon::parse($value)->startOfDay(),
            default                             => null,
        };
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return match (true) {
            $value === null                     => null,
            $value instanceof DateTimeInterface => Carbon::instance($value)->toDateString(),
            is_int($value)                      => Carbon::createFromTimestamp($value)->toDateString(),
            is_string($value)                   => Carbon::parse($value)->toDateString(),
            default                             => null,
        };
    }
}
