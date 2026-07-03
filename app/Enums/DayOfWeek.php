<?php

declare(strict_types=1);

namespace App\Enums;

enum DayOfWeek: int
{
    case Sunday = 1;
    case Monday = 2;
    case Tuesday = 3;
    case Wednesday = 4;
    case Thursday = 5;
    case Friday = 6;
    case Saturday = 7;

    /**
     * @return array<int, int>
     */
    public static function values(): array
    {
        return array_map(static fn (self $day): int => $day->value, self::cases());
    }
}
