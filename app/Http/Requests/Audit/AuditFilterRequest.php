<?php

declare(strict_types=1);

namespace App\Http\Requests\Audit;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AuditFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /**
             * Filter by audit event type.
             *
             * @example updated
             */
            'event' => ['sometimes', 'string', Rule::in(['created', 'updated', 'deleted', 'restored'])],

            /**
             * Filter by the IP address that triggered the change.
             *
             * @example 203.0.113.42
             */
            'ip_address' => ['sometimes', 'ip'],

            /**
             * Only include audits recorded on or after this date.
             *
             * @example 2026-01-01
             */
            'from' => ['sometimes', 'date'],

            /**
             * Only include audits recorded on or before this date.
             *
             * @example 2026-06-30
             */
            'to' => ['sometimes', 'date', 'after_or_equal:from'],

            /**
             * Number of audits per page (1-100).
             *
             * @example 25
             */
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
