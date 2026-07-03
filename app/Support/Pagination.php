<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Bounds a client-supplied page size to a safe range so a request cannot ask
 * for an unbounded result set.
 */
final class Pagination
{
    public static function perPage(int $value, int $default = 15, int $max = 100): int
    {
        $value = $value > 0 ? $value : $default;

        return min($max, $value);
    }
}
