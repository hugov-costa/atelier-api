<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Lifecycle of a commission order, from acceptance to hand-off.
 */
enum OrderStatus: string
{
    case Pending = 'pending';
    case InProduction = 'in_production';
    case Ready = 'ready';
    case Delivered = 'delivered';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $status): string => $status->value, self::cases());
    }
}
