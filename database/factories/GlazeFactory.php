<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Glaze;
use App\Models\GlazeSupplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Glaze>
 */
class GlazeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'glaze_supplier_id' => GlazeSupplier::factory(),
            'name'              => fake()->words(2, true),
            'price'             => fake()->numberBetween(1000, 8000),
        ];
    }
}
