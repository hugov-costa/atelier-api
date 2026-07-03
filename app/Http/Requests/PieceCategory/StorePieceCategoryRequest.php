<?php

declare(strict_types=1);

namespace App\Http\Requests\PieceCategory;

use App\Models\PieceCategory;
use Illuminate\Foundation\Http\FormRequest;

class StorePieceCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', PieceCategory::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /**
             * Optional date after which the category is retired (YYYY-mm-dd).
             *
             * @example 2026-12-31
             */
            'available_until' => ['nullable', 'date'],

            /**
             * Category name.
             *
             * @example Coleção de Natal
             */
            'name' => ['required', 'string', 'min:2', 'max:255'],

            /**
             * Profit margin multiplier for pieces in this category.
             *
             * @example 2.5
             */
            'profit_margin' => ['required', 'numeric', 'min:0', 'max:9999.99'],
        ];
    }
}
