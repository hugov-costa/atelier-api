<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\FiringCycle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FiringCycle>
 */
class FiringCycleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'cycle'          => fake()->numberBetween(1, 3),
            'duration'       => fake()->numberBetween(120, 900),
            'name'           => fake()->words(2, true),
            'price_per_unit' => fake()->numberBetween(200, 3000),
            'temperature'    => fake()->randomFloat(2, 800, 1300),
        ];
    }
}
