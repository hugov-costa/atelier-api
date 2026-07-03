<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Validates that a date/time (or time-of-day) value is strictly later than a
 * reference value. The reference may be an incoming field or a value already
 * persisted on the model, so it works for both create and partial-update flows.
 */
class AfterDateTime implements ValidationRule
{
    public function __construct(private Carbon|string|null $reference) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (blank($value) || blank($this->reference)) {
            return;
        }

        if (! is_string($value) && ! $value instanceof Carbon) {
            return;
        }

        try {
            $target = $value instanceof Carbon ? $value : Carbon::parse($value);
            $reference = $this->reference instanceof Carbon ? $this->reference : Carbon::parse($this->reference);
        } catch (Throwable) {
            return;
        }

        if (! $target->greaterThan($reference)) {
            $fail('The :attribute must be later than the start.');
        }
    }
}
