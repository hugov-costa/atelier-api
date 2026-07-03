<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Clay;
use App\Models\ClaySupplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Clay>
 */
class ClayFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'clay_supplier_id' => ClaySupplier::factory(),
            'name'             => fake()->words(2, true),
            'price'            => fake()->numberBetween(500, 5000),
        ];
    }
}
