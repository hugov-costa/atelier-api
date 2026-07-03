<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Glaze;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Glaze
 */
class GlazeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->ulid,
            'created_at'        => $this->created_at,
            'description'       => $this->description,
            'glaze_supplier_id' => $this->glazeSupplier->ulid,
            'name'              => $this->name,
            'price'             => $this->price,
            'updated_at'        => $this->updated_at,
        ];
    }
}
