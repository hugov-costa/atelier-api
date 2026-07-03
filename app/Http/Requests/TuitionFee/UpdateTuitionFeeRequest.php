<?php

declare(strict_types=1);

namespace App\Http\Requests\TuitionFee;

use App\Models\TuitionFee;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTuitionFeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $tuitionFee = $this->route('tuition_fee');
        $actor = $this->user();

        return $tuitionFee instanceof TuitionFee && $actor !== null && $actor->can('update', $tuitionFee);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /**
             * Whether the tuition is paid. Setting it true stamps `paid_at`; false clears it.
             *
             * @example true
             */
            'is_paid' => ['required', 'boolean'],
        ];
    }
}
