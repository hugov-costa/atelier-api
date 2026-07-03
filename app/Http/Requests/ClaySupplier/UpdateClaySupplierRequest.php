<?php

declare(strict_types=1);

namespace App\Http\Requests\ClaySupplier;

use App\Models\ClaySupplier;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateClaySupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        $supplier = $this->route('clay_supplier');
        $actor = $this->user();

        return $supplier instanceof ClaySupplier && $actor !== null && $actor->can('update', $supplier);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $supplier = $this->route('clay_supplier');
        $ignore = $supplier instanceof ClaySupplier ? $supplier->getKey() : null;

        return [
            /**
             * Supplier contact email.
             *
             * @example contato@argilas.com
             */
            'email' => [
                'sometimes',
                'string',
                'email',
                'max:255',
                Rule::unique('clay_suppliers', 'email')->ignore($ignore)->whereNull('deleted_at'),
            ],

            /**
             * Supplier name.
             *
             * @example Argilas do Vale
             */
            'name' => [
                'sometimes',
                'string',
                'min:2',
                'max:255',
                Rule::unique('clay_suppliers', 'name')->ignore($ignore)->whereNull('deleted_at'),
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
