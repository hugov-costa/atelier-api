<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Impersonation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Impersonation
 */
class ImpersonationResource extends JsonResource
{
    /**
     * Transform the impersonation record into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'impersonator' => $this->impersonator?->name,
            'reason'       => $this->reason,
            'ip_address'   => $this->ip_address,
            'started_at'   => $this->created_at?->toIso8601String(),
            'expires_at'   => $this->expires_at->toIso8601String(),
            'ended_at'     => $this->ended_at?->toIso8601String(),
        ];
    }
}
