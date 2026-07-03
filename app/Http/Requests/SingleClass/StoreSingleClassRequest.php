<?php

declare(strict_types=1);

namespace App\Http\Requests\SingleClass;

use App\Models\SingleClass;
use App\Support\Rules;
use Illuminate\Foundation\Http\FormRequest;

class StoreSingleClassRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', SingleClass::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /**
             * End date and time (YYYY-mm-dd HH:ii:ss). Must be after the start.
             *
             * @example 2026-03-10 11:00:00
             */
            'end_datetime' => ['required', 'date', 'after:start_datetime'],

            /**
             * Whether the class replaces a previously missed one.
             *
             * @example false
             */
            'is_replacement' => ['required', 'boolean'],

            /**
             * Price in cents.
             *
             * @example 5000
             */
            'price' => ['nullable', 'integer', 'min:0', 'max:9999999'],

            /**
             * Start date and time (YYYY-mm-dd HH:ii:ss).
             *
             * @example 2026-03-10 10:00:00
             */
            'start_datetime' => ['required', 'date'],

            /**
             * Public ids of the attending students (at least one).
             *
             * @example ["01J9Z3K7QffEXAMPLEULID0000"]
             */
            'user_ids' => ['required', 'array', 'min:1', 'max:100'],

            'user_ids.*' => ['string', 'distinct', Rules::activeUser()],
        ];
    }
}
