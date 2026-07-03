<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Enrollment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Enrollment
 */
class EnrollmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                           => $this->ulid,
            'annual_fee'                   => $this->annual_fee,
            'annual_fee_due_date'          => $this->annual_fee_due_date?->toDateString(),
            'annual_fee_is_paid'           => $this->annual_fee_is_paid,
            'annual_fee_paid_at'           => $this->annual_fee_paid_at,
            'created_at'                   => $this->created_at,
            'is_exempt_from_annual_fee'    => $this->is_exempt_from_annual_fee,
            'is_exempt_from_piece_charges' => $this->is_exempt_from_piece_charges,
            'is_exempt_from_tuition_fee'   => $this->is_exempt_from_tuition_fee,
            'updated_at'                   => $this->updated_at,
            'user_id'                      => $this->user->ulid,
        ];
    }
}
