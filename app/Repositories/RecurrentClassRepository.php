<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\RecurrentClass;

class RecurrentClassRepository
{
    /**
     * Whether any recurrent class on the given weekday overlaps the [start, end)
     * interval, optionally excluding the class being updated.
     */
    public function hasOverlap(int $day, string $start, string $end, ?int $excludeId = null): bool
    {
        $query = RecurrentClass::query()
            ->where('day_of_the_week', $day)
            ->where('start_time', '<', $end)
            ->where('end_time', '>', $start);

        if ($excludeId !== null) {
            $query->whereKeyNot($excludeId);
        }

        return $query->exists();
    }
}
