<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PieceKind;
use App\Models\Clay;
use App\Models\Piece;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Piece>
 */
class PieceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $productionCost = fake()->numberBetween(500, 20000);

        return [
            'clay_id'           => Clay::factory(),
            'glaze_id'          => null,
            'piece_category_id' => null,
            'user_id'           => User::factory(),
            'base_cost'         => fake()->numberBetween(0, 2000),
            'clay_amount'       => fake()->randomFloat(3, 0.1, 5),
            'clay_unit_price'   => fake()->numberBetween(500, 5000),
            'glaze_amount'      => null,
            'glaze_unit_price'  => null,
            'kind'              => PieceKind::Commission,
            'name'              => fake()->words(2, true),
            'price'             => $productionCost,
            'production_cost'   => $productionCost,
            'profit_margin'     => 1.0,
        ];
    }

    /**
     * A student's own class work: charged materials + firing only.
     */
    public function student(): self
    {
        return $this->state(fn (): array => [
            'kind'          => PieceKind::Student,
            'base_cost'     => 0,
            'profit_margin' => null,
        ]);
    }
}
