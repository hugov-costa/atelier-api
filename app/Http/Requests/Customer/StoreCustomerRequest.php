<?php

declare(strict_types=1);

namespace App\Http\Requests\Customer;

use App\Models\Customer;
use Illuminate\Foundation\Http\FormRequest;

class StoreCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Customer::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /**
             * Free-text notes about the customer.
             *
             * @example Cliente recorrente de peças utilitárias.
             */
            'description' => ['nullable', 'string', 'max:1000'],

            /**
             * Contact email.
             *
             * @example maria@example.com
             */
            'email' => ['nullable', 'email', 'max:255'],

            /**
             * Customer name.
             *
             * @example Maria Oliveira
             */
            'name' => ['required', 'string', 'max:255'],

            /**
             * Contact phone.
             *
             * @example 11987654321
             */
            'phone' => ['nullable', 'string', 'max:255'],
        ];
    }
}
