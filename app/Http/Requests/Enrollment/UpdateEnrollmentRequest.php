<?php

declare(strict_types=1);

namespace App\Http\Requests\Enrollment;

use App\Models\Enrollment;
use Illuminate\Foundation\Http\FormRequest;

class UpdateEnrollmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $enrollment = $this->route('enrollment');
        $actor = $this->user();

        return $enrollment instanceof Enrollment && $actor !== null && $actor->can('update', $enrollment);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /**
             * When the annual fee falls due (drives reminders).
             *
             * @example 2026-07-01
             */
            'annual_fee_due_date' => ['sometimes', 'nullable', 'date'],

            /**
             * Whether the annual fee has already been paid.
             *
             * @example true
             */
            'annual_fee_is_paid' => ['sometimes', 'boolean'],

            /**
             * Whether the student is exempt from the annual enrollment fee.
             *
             * @example false
             */
            'is_exempt_from_annual_fee' => ['sometimes', 'boolean'],

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
            'is_exempt_from_tuition_fee' => ['sometimes', 'boolean'],
        ];
    }
}
