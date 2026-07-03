<?php

declare(strict_types=1);

namespace App\Http\Requests\RecurrentClass;

use App\Models\RecurrentClass;
use App\Repositories\RecurrentClassRepository;
use App\Rules\AfterDateTime;
use App\Rules\BeforeDateTime;
use App\Support\Rules;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class UpdateRecurrentClassRequest extends FormRequest
{
    public function authorize(): bool
    {
        $class = $this->route('recurrent_class');
        $actor = $this->user();

        return $class instanceof RecurrentClass && $actor !== null && $actor->can('update', $class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        [$day, $start, $end] = $this->effectiveSlot();

        return [
            /**
             * Day of the week (1 = Sunday … 7 = Saturday).
             *
             * @example 3
             */
            'day_of_the_week' => ['sometimes', 'integer', 'between:1,7'],

            /**
             * End time (HH:ii:ss). Must be after the start.
             *
             * @example 11:30:00
             */
            'end_time' => ['sometimes', 'date_format:H:i:s', new AfterDateTime($start)],

            /**
             * Start time (HH:ii:ss). Must be before the end.
             *
             * @example 10:30:00
             */
            'start_time' => ['sometimes', 'date_format:H:i:s', new BeforeDateTime($end)],

            /**
             * Public ids of the attending students (replaces the current set).
             *
             * @example ["01J9Z3K7QffEXAMPLEULID0000"]
             */
            'user_ids' => ['sometimes', 'array', 'min:1', 'max:100'],

            'user_ids.*' => ['string', 'distinct', Rules::activeUser()],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            [$day, $start, $end] = $this->effectiveSlot();
            $class = $this->route('recurrent_class');
            $excludeId = $class instanceof RecurrentClass ? $class->id : null;

            if ($day === null || $start === null || $end === null) {
                return;
            }

            if (app(RecurrentClassRepository::class)->hasOverlap($day, $start, $end, $excludeId)) {
                $validator->errors()->add(
                    'end_time',
                    'Another recurrent class already overlaps this weekday and time range.',
                );
            }
        });
    }

    /**
     * The slot to validate, falling back to the persisted values for any field
     * the partial update leaves out.
     *
     * @return array{0: int|null, 1: string|null, 2: string|null}
     */
    private function effectiveSlot(): array
    {
        $class = $this->route('recurrent_class');

        $day = $this->has('day_of_the_week')
            ? $this->integer('day_of_the_week')
            : ($class instanceof RecurrentClass ? $class->day_of_the_week : null);

        $startInput = $this->input('start_time');
        $start = is_string($startInput)
            ? $startInput
            : ($class instanceof RecurrentClass ? $class->start_time : null);

        $endInput = $this->input('end_time');
        $end = is_string($endInput)
            ? $endInput
            : ($class instanceof RecurrentClass ? $class->end_time : null);

        return [$day, $start, $end];
    }
}
