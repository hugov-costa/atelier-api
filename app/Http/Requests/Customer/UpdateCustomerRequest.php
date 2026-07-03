<?php

declare(strict_types=1);

namespace App\Http\Requests\Customer;

use App\Models\Customer;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        $customer = $this->route('customer');
        $actor = $this->user();

        return $customer instanceof Customer && $actor !== null && $actor->can('update', $customer);
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
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],

            /**
             * Contact email.
             *
             * @example maria@example.com
             */
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],

            /**
             * Customer name.
             *
             * @example Maria Oliveira
             */
            'name' => ['sometimes', 'string', 'max:255'],

            /**
             * Contact phone.
             *
             * @example 11987654321
             */
            'phone' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
