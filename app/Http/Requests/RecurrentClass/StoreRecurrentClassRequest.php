<?php

declare(strict_types=1);

namespace App\Http\Requests\RecurrentClass;

use App\Models\RecurrentClass;
use App\Repositories\RecurrentClassRepository;
use App\Rules\AfterDateTime;
use App\Support\Rules;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class StoreRecurrentClassRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', RecurrentClass::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /**
             * Day of the week (1 = Sunday … 7 = Saturday).
             *
             * @example 2
             */
            'day_of_the_week' => ['required', 'integer', 'between:1,7'],

            /**
             * End time (HH:ii:ss). Must be after the start.
             *
             * @example 11:00:00
             */
            'end_time' => ['required', 'date_format:H:i:s', new AfterDateTime($this->stringOrNull('start_time'))],

            /**
             * Start time (HH:ii:ss).
             *
             * @example 10:00:00
             */
            'start_time' => ['required', 'date_format:H:i:s'],

            /**
             * Public ids of the attending students (at least one).
             *
             * @example ["01J9Z3K7QffEXAMPLEULID0000"]
             */
            'user_ids' => ['required', 'array', 'min:1', 'max:100'],

            'user_ids.*' => ['string', 'distinct', Rules::activeUser()],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $start = $this->stringOrNull('start_time');
            $end = $this->stringOrNull('end_time');

            if ($start === null || $end === null) {
                return;
            }

            if (app(RecurrentClassRepository::class)->hasOverlap($this->integer('day_of_the_week'), $start, $end)) {
                $validator->errors()->add(
                    'end_time',
                    'Another recurrent class already overlaps this weekday and time range.',
                );
            }
        });
    }

    private function stringOrNull(string $key): ?string
    {
        $value = $this->input($key);

        return is_string($value) ? $value : null;
    }
}
