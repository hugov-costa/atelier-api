<?php

declare(strict_types=1);

namespace App\Http\Requests\Clay;

use App\Models\Clay;
use App\Support\Rules;
use Illuminate\Foundation\Http\FormRequest;

class StoreClayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Clay::class) ?? false;
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
            'clay_supplier_id' => ['required', 'string', Rules::existsActive('clay_suppliers', 'ulid')],

            /**
             * Optional notes about the clay.
             *
             * @example Grés de alta temperatura
             */
            'description' => ['nullable', 'string', 'max:1000'],

            /**
             * Clay name.
             *
             * @example Grés branco
             */
            'name' => ['required', 'string', 'min:2', 'max:255'],

            /**
             * Price per kilogram in cents.
             *
             * @example 1200
             */
            'price' => ['required', 'integer', 'min:0', 'max:9999999'],
        ];
    }
}
