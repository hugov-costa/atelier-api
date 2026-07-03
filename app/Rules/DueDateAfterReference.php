<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Ensures a bill's due date is not earlier than the first day of its reference
 * month, so an expense cannot be filed as due before the period it belongs to.
 */
class DueDateAfterReference implements ValidationRule
{
    public function __construct(
        private ?int $referenceMonth,
        private ?int $referenceYear,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($this->referenceMonth === null || $this->referenceYear === null) {
            return;
        }

        if (! is_string($value) && ! is_numeric($value)) {
            return;
        }

        try {
            $reference = Carbon::createFromDate($this->referenceYear, $this->referenceMonth, 1)->startOfMonth();
            $dueDate = Carbon::parse((string) $value)->startOfDay();
        } catch (Throwable) {
            return;
        }

        if ($dueDate->lessThan($reference)) {
            $fail('The due date cannot be earlier than its reference month.');
        }
    }
}
