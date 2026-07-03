<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Bill;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Bill
 */
class BillResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->ulid,
            'created_at'      => $this->created_at,
            'description'     => $this->description,
            'due_date'        => $this->due_date->toDateString(),
            'is_recurrent'    => $this->is_recurrent,
            'name'            => $this->name,
            'reference_month' => $this->reference_month,
            'reference_year'  => $this->reference_year,
            'updated_at'      => $this->updated_at,
            'value'           => $this->value,
        ];
    }
}
