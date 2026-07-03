<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Customer
 */
class CustomerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->ulid,
            'created_at'  => $this->created_at,
            'description' => $this->description,
            'email'       => $this->email,
            'name'        => $this->name,
            'phone'       => $this->phone,
            'updated_at'  => $this->updated_at,
        ];
    }
}
