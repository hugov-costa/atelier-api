<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\SingleClass;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SingleClass
 */
class SingleClassResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->ulid,
            'created_at'     => $this->created_at,
            'end_datetime'   => $this->end_datetime,
            'is_replacement' => $this->is_replacement,
            'price'          => $this->price,
            'start_datetime' => $this->start_datetime,
            'updated_at'     => $this->updated_at,
            'users'          => $this->whenLoaded('users', fn () => $this->users
                ->map(fn (User $user): array => ['id' => $user->ulid, 'name' => $user->name])
                ->all()),
        ];
    }
}
