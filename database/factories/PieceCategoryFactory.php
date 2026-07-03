<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\PieceCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PieceCategory>
 */
class PieceCategoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'available_until' => fake()->boolean(70)
                ? fake()->dateTimeBetween('now', '+1 year')->format('Y-m-d')
                : null,
            'name'          => fake()->unique()->words(2, true),
            'profit_margin' => fake()->randomFloat(2, 1, 5),
        ];
    }
}
