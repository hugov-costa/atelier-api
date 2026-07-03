<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\RecurrentClass;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin RecurrentClass
 */
class RecurrentClassResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->ulid,
            'created_at'      => $this->created_at,
            'day_of_the_week' => $this->day_of_the_week,
            'end_time'        => $this->end_time,
            'start_time'      => $this->start_time,
            'updated_at'      => $this->updated_at,
            'users'           => $this->whenLoaded('users', fn () => $this->users
                ->map(fn (User $user): array => ['id' => $user->ulid, 'name' => $user->name])
                ->all()),
        ];
    }
}
