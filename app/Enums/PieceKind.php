<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Distinguishes the two economically different kinds of piece the atelier makes.
 *
 * - Commission: made by the atelier (usually to order) and sold, so its price
 *   includes the studio base cost and a profit margin.
 * - Student: the student's own class work. The studio overhead is already paid
 *   through the tuition/enrollment, so the student is only charged for the
 *   materials and firing the piece consumed (no base cost, no margin).
 */
enum PieceKind: string
{
    case Commission = 'commission';
    case Student = 'student';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $kind): string => $kind->value, self::cases());
    }
}
