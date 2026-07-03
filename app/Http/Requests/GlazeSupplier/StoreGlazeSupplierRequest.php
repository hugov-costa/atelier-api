<?php

declare(strict_types=1);

namespace App\Http\Requests\GlazeSupplier;

use App\Models\GlazeSupplier;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGlazeSupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', GlazeSupplier::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /**
             * Supplier contact email.
             *
             * @example contato@esmaltes.com
             */
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('glaze_suppliers', 'email')->whereNull('deleted_at'),
            ],

            /**
             * Supplier name.
             *
             * @example Esmaltes Cerâmicos
             */
            'name' => [
                'required',
                'string',
                'min:2',
                'max:255',
                Rule::unique('glaze_suppliers', 'name')->whereNull('deleted_at'),
            ],

            /**
             * Contact phone with area code, digits only (10-11 chars).
             *
             * @example 11987654321
             */
            'phone' => ['required', 'string', 'regex:/^[0-9]{10,11}$/'],
        ];
    }
}
