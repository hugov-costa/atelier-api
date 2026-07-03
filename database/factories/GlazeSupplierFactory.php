<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\GlazeSupplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GlazeSupplier>
 */
class GlazeSupplierFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'email' => fake()->unique()->companyEmail(),
            'name'  => fake()->unique()->company(),
            'phone' => (string) fake()->numerify('119########'),
        ];
    }
}
