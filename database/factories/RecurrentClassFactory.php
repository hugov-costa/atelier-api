<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\RecurrentClass;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecurrentClass>
 */
class RecurrentClassFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startHour = fake()->numberBetween(7, 18);
        $endHour = $startHour + fake()->numberBetween(1, 2);

        return [
            'day_of_the_week' => fake()->numberBetween(1, 7),
            'end_time'        => sprintf('%02d:00:00', $endHour),
            'start_time'      => sprintf('%02d:00:00', $startHour),
        ];
    }
}
