<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/**
 * Shared validation rule builders for the atelier domain.
 */
final class Rules
{
    /**
     * Requires the value to be the public ULID of an active, non-deleted account.
     * Admins may be students too, so only the active flag is checked — not the role.
     */
    public static function activeUser(): Exists
    {
        return Rule::exists('users', 'ulid')
            ->where('is_active', true)
            ->whereNull('deleted_at');
    }

    /**
     * Requires the value to exist in a soft-deletable table and not be trashed, so a
     * retired (soft-deleted) record can never be referenced by a new row. Use instead
     * of the raw `exists:table,column` rule, which also matches soft-deleted rows.
     */
    public static function existsActive(string $table, string $column = 'ulid'): Exists
    {
        return Rule::exists($table, $column)->whereNull('deleted_at');
    }
}
