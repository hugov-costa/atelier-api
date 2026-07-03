<?php

declare(strict_types=1);

namespace App\Http\Requests\Report;

use Illuminate\Foundation\Http\FormRequest;

class MonthlyReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewReports') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /**
             * Report month, 1-12 (defaults to the current month).
             *
             * @example 3
             */
            'month' => ['sometimes', 'integer', 'between:1,12'],

            /**
             * Report year (defaults to the current year).
             *
             * @example 2026
             */
            'year' => ['sometimes', 'integer', 'between:2000,2100'],
        ];
    }
}
