<?php

declare(strict_types=1);

namespace App\Http\Requests\PieceCharge;

use App\Models\PieceCharge;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePieceChargeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $charge = $this->route('piece_charge');
        $actor = $this->user();

        return $charge instanceof PieceCharge && $actor !== null && $actor->can('update', $charge);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /**
             * Whether the charge has been paid. Normally a charge settles with its
             * tuition; this is the manual override (e.g. tuition-exempt students).
             *
             * @example true
             */
            'is_paid' => ['required', 'boolean'],
        ];
    }
}
