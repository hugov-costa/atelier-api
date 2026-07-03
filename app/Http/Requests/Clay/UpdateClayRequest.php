<?php

declare(strict_types=1);

namespace App\Http\Requests\Clay;

use App\Models\Clay;
use App\Support\Rules;
use Illuminate\Foundation\Http\FormRequest;

class UpdateClayRequest extends FormRequest
{
    public function authorize(): bool
    {
        $clay = $this->route('clay');
        $actor = $this->user();

        return $clay instanceof Clay && $actor !== null && $actor->can('update', $clay);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /**
             * Public id of the clay supplier.
             *
             * @example 01J9Z3K7QffEXAMPLEULID0000
             */
            'clay_supplier_id' => ['sometimes', 'string', Rules::existsActive('clay_suppliers', 'ulid')],

            /**
             * Optional notes about the clay.
             *
             * @example Grés de alta temperatura
             */
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],

            /**
             * Clay name.
             *
             * @example Grés branco
             */
            'name' => ['sometimes', 'string', 'min:2', 'max:255'],

            /**
             * Price per kilogram in cents.
             *
             * @example 1200
             */
            'price' => ['sometimes', 'integer', 'min:0', 'max:9999999'],
        ];
    }
}
