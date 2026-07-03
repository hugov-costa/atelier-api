<?php

declare(strict_types=1);

namespace App\Http\Requests\Setting;

use App\Models\Setting;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', Setting::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /**
             * Annual enrollment cost in cents.
             *
             * @example 15000
             */
            'annual_enrollment_cost' => ['sometimes', 'integer', 'min:0', 'max:9999999'],

            /**
             * Baseline production cost in cents added to every piece.
             *
             * @example 500
             */
            'base_cost' => ['sometimes', 'integer', 'min:0', 'max:9999999'],

            /**
             * Multiplier applied per full kilogram of clay, before profit margin.
             *
             * @example 1.25
             */
            'clay_amount_multiplier' => ['sometimes', 'numeric', 'min:0', 'max:9999.99'],

            /**
             * Default profit margin multiplier applied after all costs.
             *
             * @example 2.0
             */
            'default_profit_margin' => ['sometimes', 'numeric', 'min:0', 'max:9999.99'],

            /**
             * Grace window (in days) after a cycle's due date within which a new student
             * piece is billed on the next tuition; pieces made later are pushed one cycle further.
             *
             * @example 20
             */
            'piece_charge_billing_grace_days' => ['sometimes', 'integer', 'min:0', 'max:31'],

            /**
             * Day of the month (1-28) when monthly tuition fees fall due.
             *
             * @example 10
             */
            'tuition_fee_due_day_of_month' => ['sometimes', 'integer', 'min:1', 'max:28'],

            /**
             * Monthly tuition cost in cents.
             *
             * @example 20000
             */
            'tuition_monthly_cost' => ['sometimes', 'integer', 'min:0', 'max:9999999'],
        ];
    }
}
