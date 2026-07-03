<?php

declare(strict_types=1);

namespace App\Http\Requests\Glaze;

use App\Models\Glaze;
use App\Support\Rules;
use Illuminate\Foundation\Http\FormRequest;

class UpdateGlazeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $glaze = $this->route('glaze');
        $actor = $this->user();

        return $glaze instanceof Glaze && $actor !== null && $actor->can('update', $glaze);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /**
             * Optional notes about the glaze.
             *
             * @example Acabamento brilhante
             */
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],

            /**
             * Public id of the glaze supplier.
             *
             * @example 01J9Z3K7QffEXAMPLEULID0000
             */
            'glaze_supplier_id' => ['sometimes', 'string', Rules::existsActive('glaze_suppliers', 'ulid')],

            /**
             * Glaze name.
             *
             * @example Azul cobalto
             */
            'name' => ['sometimes', 'string', 'min:2', 'max:255'],

            /**
             * Price per liter in cents.
             *
             * @example 4500
             */
            'price' => ['sometimes', 'integer', 'min:0', 'max:9999999'],
        ];
    }
}
