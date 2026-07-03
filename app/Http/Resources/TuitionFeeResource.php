<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\TuitionFee;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TuitionFee
 */
class TuitionFeeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->ulid,
            'amount'        => $this->amount,
            'created_at'    => $this->created_at,
            'due_date'      => $this->due_date->toDateString(),
            'enrollment_id' => $this->enrollment->ulid,
            'is_paid'       => $this->paid_at !== null,
            'paid_at'       => $this->paid_at,
            'updated_at'    => $this->updated_at,
        ];
    }
}
