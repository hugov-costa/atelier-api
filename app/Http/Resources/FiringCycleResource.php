<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\FiringCycle;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin FiringCycle
 */
class FiringCycleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->ulid,
            'created_at'     => $this->created_at,
            'cycle'          => $this->cycle,
            'duration'       => $this->duration,
            'name'           => $this->name,
            'price_per_unit' => $this->price_per_unit,
            'temperature'    => $this->temperature,
            'updated_at'     => $this->updated_at,
        ];
    }
}
