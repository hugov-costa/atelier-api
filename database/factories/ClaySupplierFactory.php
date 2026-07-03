<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ClaySupplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClaySupplier>
 */
class ClaySupplierFactory extends Factory
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
