<?php

declare(strict_types=1);

namespace App\Http\Requests\Piece;

use App\Models\Piece;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePieceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $piece = $this->route('piece');
        $actor = $this->user();

        return $piece instanceof Piece && $actor !== null && $actor->can('update', $piece);
    }

    /**
     * A piece is a one-off physical object whose composition and pricing are
     * snapshotted at creation, so only administrative fields may be updated.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /**
             * Piece name.
             *
             * @example Tigela pequena
             */
            'name' => ['sometimes', 'string', 'min:2', 'max:255'],
        ];
    }
}
