<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\PieceCategory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PieceCategory
 */
class PieceCategoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->ulid,
            'available_until' => $this->available_until?->toDateString(),
            'created_at'      => $this->created_at,
            'is_available'    => $this->isAvailable(),
            'name'            => $this->name,
            'profit_margin'   => $this->profit_margin,
            'updated_at'      => $this->updated_at,
        ];
    }
}
