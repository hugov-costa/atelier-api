<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Notifications\DatabaseNotification;

/**
 * @mixin DatabaseNotification
 */
class NotificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $data */
        $data = $this->data;

        return [
            'id'         => $this->id,
            'created_at' => $this->created_at,
            'data'       => $data,
            'read_at'    => $this->read_at,
            'type'       => is_string($data['type'] ?? null) ? $data['type'] : $this->type,
        ];
    }
}
