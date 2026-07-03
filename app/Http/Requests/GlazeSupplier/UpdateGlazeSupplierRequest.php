<?php

declare(strict_types=1);

namespace App\Http\Requests\GlazeSupplier;

use App\Models\GlazeSupplier;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGlazeSupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        $supplier = $this->route('glaze_supplier');
        $actor = $this->user();

        return $supplier instanceof GlazeSupplier && $actor !== null && $actor->can('update', $supplier);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $supplier = $this->route('glaze_supplier');
        $ignore = $supplier instanceof GlazeSupplier ? $supplier->getKey() : null;

        return [
            /**
             * Supplier contact email.
             *
             * @example contato@esmaltes.com
             */
            'email' => [
                'sometimes',
                'string',
                'email',
                'max:255',
                Rule::unique('glaze_suppliers', 'email')->ignore($ignore)->whereNull('deleted_at'),
            ],

            /**
             * Supplier name.
             *
             * @example Esmaltes Cerâmicos
             */
            'name' => [
                'sometimes',
                'string',
                'min:2',
                'max:255',
                Rule::unique('glaze_suppliers', 'name')->ignore($ignore)->whereNull('deleted_at'),
            ],

            /**
             * Contact phone with area code, digits only (10-11 chars).
             *
             * @example 11987654321
             */
            'phone' => ['sometimes', 'string', 'regex:/^[0-9]{10,11}$/'],
        ];
    }
}
