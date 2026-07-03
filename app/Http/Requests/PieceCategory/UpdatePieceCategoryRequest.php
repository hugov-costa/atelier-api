<?php

declare(strict_types=1);

namespace App\Http\Requests\PieceCategory;

use App\Models\PieceCategory;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePieceCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $category = $this->route('piece_category');
        $actor = $this->user();

        return $category instanceof PieceCategory && $actor !== null && $actor->can('update', $category);
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
            'available_until' => ['sometimes', 'nullable', 'date'],

            /**
             * Category name.
             *
             * @example Coleção de Natal
             */
            'name' => ['sometimes', 'string', 'min:2', 'max:255'],

            /**
             * Profit margin multiplier for pieces in this category.
             *
             * @example 2.5
             */
            'profit_margin' => ['sometimes', 'numeric', 'min:0', 'max:9999.99'],
        ];
    }
}
