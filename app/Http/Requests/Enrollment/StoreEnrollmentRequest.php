<?php

declare(strict_types=1);

namespace App\Http\Requests\Enrollment;

use App\Models\Enrollment;
use App\Support\Rules;
use Illuminate\Foundation\Http\FormRequest;

class StoreEnrollmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Enrollment::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /**
             * When the annual fee falls due (drives reminders); defaults to today.
             *
             * @example 2026-07-01
             */
            'annual_fee_due_date' => ['sometimes', 'nullable', 'date'],

            /**
             * Whether the annual fee has already been paid.
             *
             * @example false
             */
            'annual_fee_is_paid' => ['nullable', 'boolean'],

            /**
             * Whether the student is exempt from the annual enrollment fee.
             *
             * @example false
             */
            'is_exempt_from_annual_fee' => ['nullable', 'boolean'],

            /**
             * Whether the student is exempt from charges on the pieces they produce.
             *
             * @example false
             */
            'is_exempt_from_piece_charges' => ['sometimes', 'boolean'],

            /**
             * Whether the student is exempt from monthly tuition fees.
             *
             * @example false
             */
            'is_exempt_from_tuition_fee' => ['nullable', 'boolean'],

            /**
             * Public id of the enrolled student.
             *
             * @example 01J9Z3K7QffEXAMPLEULID0000
             */
            'user_id' => ['required', 'string', Rules::activeUser()],
        ];
    }
}
