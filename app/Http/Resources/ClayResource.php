<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Clay;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Clay
 */
class ClayResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->ulid,
            'clay_supplier_id' => $this->claySupplier->ulid,
            'created_at'       => $this->created_at,
            'description'      => $this->description,
            'name'             => $this->name,
            'price'            => $this->price,
            'updated_at'       => $this->updated_at,
        ];
    }
}
