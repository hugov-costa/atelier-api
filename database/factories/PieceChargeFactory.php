<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Piece;
use App\Models\PieceCharge;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PieceCharge>
 */
class PieceChargeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'piece_id' => Piece::factory()->student(),
            'user_id'  => User::factory(),
            'amount'   => fake()->numberBetween(500, 20000),
            'due_date' => fake()->dateTimeBetween('now', '+2 months')->format('Y-m-d'),
            'paid_at'  => null,
        ];
    }

    public function paid(): self
    {
        return $this->state(fn (): array => ['paid_at' => now()]);
    }
}
