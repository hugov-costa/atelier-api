<?php

declare(strict_types=1);

namespace App\Http\Requests\Piece;

use App\Enums\PieceKind;
use App\Models\Piece;
use App\Support\Rules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePieceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Piece::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /**
             * Kilograms of clay used.
             *
             * @example 1.500
             */
            'clay_amount' => ['required', 'numeric', 'min:0.001', 'max:999.999'],

            /**
             * Public id of the clay used.
             *
             * @example 01J9Z3K7QffEXAMPLEULID0000
             */
            'clay_id' => ['required', 'string', Rules::existsActive('clays', 'ulid')],

            /**
             * Public ids of the firing cycles applied (optional).
             *
             * @example ["01J9Z3K7QffEXAMPLEULID0002"]
             */
            'firing_cycle_ids' => ['nullable', 'array', 'max:50'],

            'firing_cycle_ids.*' => ['string', 'distinct', Rules::existsActive('firing_cycles', 'ulid')],

            /**
             * Liters of glaze used. Required when a glaze is given.
             *
             * @example 0.250
             */
            'glaze_amount' => ['nullable', 'required_with:glaze_id', 'numeric', 'min:0.001', 'max:999.999'],

            /**
             * Public id of the glaze used (optional).
             *
             * @example 01J9Z3K7QffEXAMPLEULID0001
             */
            'glaze_id' => ['nullable', 'string', Rules::existsActive('glazes', 'ulid')],

            /**
             * Whether the piece is a commission (sold by the atelier, includes base
             * cost and margin) or a student's own class work (materials only).
             *
             * @example commission
             */
            'kind' => ['required', Rule::enum(PieceKind::class)],

            /**
             * Piece name.
             *
             * @example Tigela pequena
             */
            'name' => ['required', 'string', 'min:2', 'max:255'],

            /**
             * Public id of the category (optional; controls the profit margin).
             *
             * @example 01J9Z3K7QffEXAMPLEULID0003
             */
            'piece_category_id' => ['nullable', 'string', Rules::existsActive('piece_categories', 'ulid')],

            /**
             * Public id of the user who made the piece (artist or student).
             *
             * @example 01J9Z3K7QffEXAMPLEULID0004
             */
            'user_id' => ['required', 'string', Rules::activeUser()],
        ];
    }
}
