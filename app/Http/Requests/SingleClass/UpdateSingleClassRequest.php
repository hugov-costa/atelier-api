<?php

declare(strict_types=1);

namespace App\Http\Requests\SingleClass;

use App\Models\SingleClass;
use App\Rules\AfterDateTime;
use App\Rules\BeforeDateTime;
use App\Support\Rules;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSingleClassRequest extends FormRequest
{
    public function authorize(): bool
    {
        $class = $this->route('single_class');
        $actor = $this->user();

        return $class instanceof SingleClass && $actor !== null && $actor->can('update', $class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $class = $this->route('single_class');

        $startInput = $this->input('start_datetime');
        $endInput = $this->input('end_datetime');

        $effectiveStart = is_string($startInput) ? $startInput
            : ($class instanceof SingleClass ? $class->start_datetime : null);
        $effectiveEnd = is_string($endInput) ? $endInput
            : ($class instanceof SingleClass ? $class->end_datetime : null);

        return [
            /**
             * End date and time (YYYY-mm-dd HH:ii:ss). Must be after the start.
             *
             * @example 2026-03-10 11:30:00
             */
            'end_datetime' => ['sometimes', 'date', new AfterDateTime($effectiveStart)],

            /**
             * Whether the class replaces a previously missed one.
             *
             * @example false
             */
            'is_replacement' => ['sometimes', 'boolean'],

            /**
             * Price in cents.
             *
             * @example 6000
             */
            'price' => ['sometimes', 'integer', 'min:0', 'max:9999999'],

            /**
             * Start date and time (YYYY-mm-dd HH:ii:ss). Must be before the end.
             *
             * @example 2026-03-10 10:30:00
             */
            'start_datetime' => ['sometimes', 'date', new BeforeDateTime($effectiveEnd)],

            /**
             * Public ids of the attending students (replaces the current set).
             *
             * @example ["01J9Z3K7QffEXAMPLEULID0000"]
             */
            'user_ids' => ['sometimes', 'array', 'min:1', 'max:100'],

            'user_ids.*' => ['string', 'distinct', Rules::activeUser()],
        ];
    }
}
