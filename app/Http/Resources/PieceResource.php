<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\FiringCycle;
use App\Models\Piece;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Piece
 */
class PieceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->ulid,
            'base_cost'       => $this->base_cost,
            'clay_amount'     => $this->clay_amount,
            'clay_id'         => $this->clay->ulid,
            'clay_unit_price' => $this->clay_unit_price,
            'created_at'      => $this->created_at,
            'firing_cycles'   => $this->whenLoaded('firingCycles', fn () => $this->firingCycles
                ->map(fn (FiringCycle $cycle): array => [
                    'id'    => $cycle->ulid,
                    'name'  => $cycle->name,
                    'price' => $this->pivotPrice($cycle),
                ])->all()),
            'glaze_amount'      => $this->glaze_amount,
            'glaze_id'          => $this->glaze?->ulid,
            'glaze_unit_price'  => $this->glaze_unit_price,
            'kind'              => $this->kind->value,
            'name'              => $this->name,
            'piece_category_id' => $this->category?->ulid,
            'price'             => $this->price,
            'production_cost'   => $this->production_cost,
            'profit_margin'     => $this->profit_margin,
            'updated_at'        => $this->updated_at,
            'user_id'           => $this->user->ulid,
        ];
    }

    private function pivotPrice(FiringCycle $cycle): int
    {
        $pivot = $cycle->getAttribute('pivot');

        if (! $pivot instanceof Pivot) {
            return 0;
        }

        $price = $pivot->getAttribute('price');

        return is_numeric($price) ? (int) $price : 0;
    }
}
