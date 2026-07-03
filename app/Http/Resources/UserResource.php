<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * @mixin User
 */
class UserResource extends JsonResource
{
    /**
     * Transform the user into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $impersonatorId = $request->attributes->get('impersonator_id');

        return [
            'id'             => $this->ulid,
            'name'           => $this->name,
            'email'          => $this->email,
            'role'           => $this->role->value,
            'is_active'      => $this->is_active,
            'admission_date' => $this->admission_date?->toDateString(),
            'birthday'       => $this->birthday?->toDateString(),
            'phone'          => $this->phone,
            'has_password'   => $this->password !== null,
            'avatar_url'     => $this->avatar_path !== null
                ? Storage::disk('minio_public')->url($this->avatar_path)
                : null,
            'two_factor_enabled' => $this->two_factor_confirmed_at !== null,
            'email_verified_at'  => $this->email_verified_at,
            'created_at'         => $this->created_at,
            'updated_at'         => $this->updated_at,
            'impersonated_by'    => is_string($impersonatorId) ? $impersonatorId : null,
        ];
    }
}
