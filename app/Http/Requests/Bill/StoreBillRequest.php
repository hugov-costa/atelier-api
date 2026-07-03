<?php

declare(strict_types=1);

namespace App\Http\Requests\Bill;

use App\Models\Bill;
use App\Rules\DueDateAfterReference;
use Illuminate\Foundation\Http\FormRequest;

class StoreBillRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Bill::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $month = $this->has('reference_month') ? $this->integer('reference_month') : null;
        $year = $this->has('reference_year') ? $this->integer('reference_year') : null;

        return [
            /**
             * Free-text description of the expense.
             *
             * @example Conta de energia referente a março
             */
            'description' => ['nullable', 'string', 'max:1000'],

            /**
             * Due date (YYYY-mm-dd). Cannot be earlier than the reference month.
             *
             * @example 2026-03-15
             */
            'due_date' => ['required', 'date', new DueDateAfterReference($month, $year)],

            /**
             * Whether the bill recurs every month.
             *
             * @example false
             */
            'is_recurrent' => ['required', 'boolean'],

            /**
             * Name of the expense.
             *
             * @example Energia
             */
            'name' => ['required', 'string', 'min:3', 'max:255'],

            /**
             * Reference month (1-12).
             *
             * @example 3
             */
            'reference_month' => ['required', 'integer', 'between:1,12'],

            /**
             * Reference year (4 digits).
             *
             * @example 2026
             */
            'reference_year' => ['required', 'integer', 'between:2000,2100'],

            /**
             * Amount in cents.
             *
             * @example 10000
             */
            'value' => ['required', 'integer', 'min:0', 'max:9999999'],
        ];
    }
}
