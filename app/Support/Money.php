<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Helpers for the integer-cents money discipline used across the atelier domain.
 */
final class Money
{
    /**
     * Coerce a possibly-null or string numeric value (e.g. a DB SUM, which returns
     * null for an empty set or a string on some drivers) to an integer number of
     * cents. Non-numeric input becomes 0.
     */
    public static function toInt(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }
}
