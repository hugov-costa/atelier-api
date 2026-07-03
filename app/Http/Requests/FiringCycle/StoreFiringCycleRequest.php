<?php

declare(strict_types=1);

namespace App\Http\Requests\FiringCycle;

use App\Models\FiringCycle;
use Illuminate\Foundation\Http\FormRequest;

class StoreFiringCycleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', FiringCycle::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /**
             * Firing pass number (e.g. 1 = bisque, 2 = glaze).
             *
             * @example 1
             */
            'cycle' => ['required', 'integer', 'min:1', 'max:255'],

            /**
             * Duration in minutes.
             *
             * @example 480
             */
            'duration' => ['required', 'integer', 'min:1', 'max:100000'],

            /**
             * Firing cycle name.
             *
             * @example Biscoito 1000°C
             */
            'name' => ['required', 'string', 'min:2', 'max:255'],

            /**
             * Cost charged per piece in cents.
             *
             * @example 800
             */
            'price_per_unit' => ['required', 'integer', 'min:0', 'max:9999999'],

            /**
             * Peak temperature in degrees Celsius.
             *
             * @example 1000
             */
            'temperature' => ['required', 'numeric', 'min:0', 'max:9999.99'],
        ];
    }
}
