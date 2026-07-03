<?php

declare(strict_types=1);

namespace App\Http\Requests\Bill;

use App\Models\Bill;
use App\Rules\DueDateAfterReference;
use Illuminate\Foundation\Http\FormRequest;

class UpdateBillRequest extends FormRequest
{
    public function authorize(): bool
    {
        $bill = $this->route('bill');
        $actor = $this->user();

        return $bill instanceof Bill && $actor !== null && $actor->can('update', $bill);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $bill = $this->route('bill');
        $month = $this->has('reference_month')
            ? $this->integer('reference_month')
            : ($bill instanceof Bill ? $bill->reference_month : null);
        $year = $this->has('reference_year')
            ? $this->integer('reference_year')
            : ($bill instanceof Bill ? $bill->reference_year : null);

        return [
            /**
             * Free-text description of the expense.
             *
             * @example Conta de água com observações
             */
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],

            /**
             * Due date (YYYY-mm-dd). Cannot be earlier than the reference month.
             *
             * @example 2026-03-20
             */
            'due_date' => ['sometimes', 'date', new DueDateAfterReference($month, $year)],

            /**
             * Whether the bill recurs every month.
             *
             * @example true
             */
            'is_recurrent' => ['sometimes', 'boolean'],

            /**
             * Name of the expense.
             *
             * @example Internet
             */
            'name' => ['sometimes', 'string', 'min:3', 'max:255'],

            /**
             * Reference month (1-12).
             *
             * @example 4
             */
            'reference_month' => ['sometimes', 'integer', 'between:1,12', 'required_with:reference_year'],

            /**
             * Reference year (4 digits).
             *
             * @example 2026
             */
            'reference_year' => ['sometimes', 'integer', 'between:2000,2100', 'required_with:reference_month'],

            /**
             * Amount in cents.
             *
             * @example 20000
             */
            'value' => ['sometimes', 'integer', 'min:0', 'max:9999999'],
        ];
    }
}
