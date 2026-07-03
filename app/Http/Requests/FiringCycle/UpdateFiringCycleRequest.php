<?php

declare(strict_types=1);

namespace App\Http\Requests\FiringCycle;

use App\Models\FiringCycle;
use Illuminate\Foundation\Http\FormRequest;

class UpdateFiringCycleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $cycle = $this->route('firing_cycle');
        $actor = $this->user();

        return $cycle instanceof FiringCycle && $actor !== null && $actor->can('update', $cycle);
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
            'cycle' => ['sometimes', 'integer', 'min:1', 'max:255'],

            /**
             * Duration in minutes.
             *
             * @example 480
             */
            'duration' => ['sometimes', 'integer', 'min:1', 'max:100000'],

            /**
             * Firing cycle name.
             *
             * @example Biscoito 1000°C
             */
            'name' => ['sometimes', 'string', 'min:2', 'max:255'],

            /**
             * Cost charged per piece in cents.
             *
             * @example 800
             */
            'price_per_unit' => ['sometimes', 'integer', 'min:0', 'max:9999999'],

            /**
             * Peak temperature in degrees Celsius.
             *
             * @example 1000
             */
            'temperature' => ['sometimes', 'numeric', 'min:0', 'max:9999.99'],
        ];
    }
}
