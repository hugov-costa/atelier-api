<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OwenIt\Auditing\Models\Audit;

/**
 * @mixin Audit
 */
class AuditResource extends JsonResource
{
    /**
     * Transform the audit into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Audit $audit */
        $audit = $this->resource;

        return [
            'id'              => $audit->getAttribute('id'),
            'event'           => $audit->getAttribute('event'),
            'auditable_type'  => $audit->getAttribute('auditable_type'),
            'auditable_id'    => $audit->getAttribute('auditable_id'),
            'user_id'         => $audit->getAttribute('user_id'),
            'old_values'      => $audit->getAttribute('old_values'),
            'new_values'      => $audit->getAttribute('new_values'),
            'ip_address'      => $audit->getAttribute('ip_address'),
            'url'             => $audit->getAttribute('url'),
            'impersonator_id' => $audit->getAttribute('impersonator_id'),
            'created_at'      => $audit->getAttribute('created_at'),
        ];
    }
}
