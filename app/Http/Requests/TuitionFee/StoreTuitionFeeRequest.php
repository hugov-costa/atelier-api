<?php

declare(strict_types=1);

namespace App\Http\Requests\TuitionFee;

use App\Models\TuitionFee;
use App\Support\Rules;
use Illuminate\Foundation\Http\FormRequest;

class StoreTuitionFeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', TuitionFee::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /**
             * Public id of the enrollment this tuition belongs to.
             *
             * @example 01J9Z3K7QffEXAMPLEULID0000
             */
            'enrollment_id' => ['required', 'string', Rules::existsActive('enrollments', 'ulid')],
        ];
    }
}
