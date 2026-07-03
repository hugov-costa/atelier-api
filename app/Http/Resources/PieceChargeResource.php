<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\PieceCharge;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PieceCharge
 */
class PieceChargeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->ulid,
            'amount'     => $this->amount,
            'created_at' => $this->created_at,
            'due_date'   => $this->due_date->toDateString(),
            'is_paid'    => $this->paid_at !== null,
            'paid_at'    => $this->paid_at,
            'piece_id'   => $this->piece->ulid,
            'updated_at' => $this->updated_at,
            'user_id'    => $this->user->ulid,
        ];
    }
}
