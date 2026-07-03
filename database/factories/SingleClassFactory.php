<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\SingleClass;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<SingleClass>
 */
class SingleClassFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = Carbon::now()->addDays(fake()->numberBetween(1, 30))->startOfHour();
        $end = $start->copy()->addHours(fake()->numberBetween(1, 3));

        return [
            'end_datetime'   => $end->toDateTimeString(),
            'is_replacement' => fake()->boolean(10),
            'price'          => fake()->numberBetween(0, 50000),
            'start_datetime' => $start->toDateTimeString(),
        ];
    }
}
