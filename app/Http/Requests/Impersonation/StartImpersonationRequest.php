<?php

declare(strict_types=1);

namespace App\Http\Requests\Impersonation;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class StartImpersonationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $actor = $this->user();

        return $actor instanceof User && $actor->isMaster();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /**
             * The reason the master is accessing the account, stored for accountability.
             *
             * @example Investigating a reported checkout failure (ticket #1234).
             */
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ];
    }
}
