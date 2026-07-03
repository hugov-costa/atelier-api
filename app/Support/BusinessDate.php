<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Returns the current date/time in the atelier's business timezone (America/Sao_Paulo).
 *
 * Storage (DB) remains in UTC, but business-date decisions — such as "what day
 * is it for the atelier" — must use the local timezone to avoid off-by-one errors
 * when pieces are registered or charges are generated during evening hours.
 *
 * Usage:
 * <code>
 * $today = BusinessDate::now();       // Carbon in America/Sao_Paulo
 * $todayString = BusinessDate::today(); // 'Y-m-d' string in America/Sao_Paulo
 * </code>
 */
final class BusinessDate
{
    private const TIMEZONE = 'America/Sao_Paulo';

    /**
     * The current moment in the atelier's business timezone.
     */
    public static function now(): Carbon
    {
        return Carbon::now(self::TIMEZONE);
    }

    /**
     * Today's date string (Y-m-d) in the atelier's business timezone.
     */
    public static function today(): string
    {
        return self::now()->toDateString();
    }
}
