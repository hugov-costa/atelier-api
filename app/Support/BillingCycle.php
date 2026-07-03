<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Pure date arithmetic for the atelier's monthly billing cycle. Kept in one place
 * so tuition fees and piece charges compute due dates identically (they must match
 * exactly for a piece charge to be settled together with its tuition).
 *
 * All methods return `Y-m-d` date strings.
 */
final class BillingCycle
{
    /**
     * The due date within the anchor's month: the configured day-of-month, clamped
     * to the month length (so day 31 falls on the last day of a short month).
     */
    public static function dueDateForMonth(Carbon $anchor, int $dueDay): string
    {
        $day = min($dueDay, (int) $anchor->copy()->endOfMonth()->format('j'));

        return $anchor->copy()->day($day)->toDateString();
    }

    /**
     * The next due date on or after the given moment.
     */
    public static function nextDueDate(int $dueDay, Carbon $from): string
    {
        $thisMonth = Carbon::parse(self::dueDateForMonth($from, $dueDay));

        // Compare at date granularity: the due day itself still counts as "on or after",
        // otherwise a moment later the same day would roll to next month.
        return $from->copy()->startOfDay()->lte($thisMonth)
            ? $thisMonth->toDateString()
            : self::dueDateForMonth($from->copy()->addMonthNoOverflow(), $dueDay);
    }

    /**
     * The tuition cycle a piece charge is billed on. A piece made within `graceDays`
     * of its cycle's due date is billed on the next tuition; one made later (too close
     * to the upcoming due date to make that invoice) is pushed one cycle further.
     */
    public static function pieceChargeDueDate(Carbon $createdAt, int $dueDay, int $graceDays): string
    {
        $thisMonthDue = Carbon::parse(self::dueDateForMonth($createdAt, $dueDay));

        $anchor = $createdAt->gte($thisMonthDue)
            ? $thisMonthDue
            : Carbon::parse(self::dueDateForMonth($createdAt->copy()->subMonthNoOverflow(), $dueDay));

        $daysSince = (int) $anchor->diffInDays($createdAt, absolute: true);
        $monthsAhead = $daysSince <= $graceDays ? 1 : 2;

        return self::dueDateForMonth($anchor->copy()->addMonthsNoOverflow($monthsAhead), $dueDay);
    }
}
