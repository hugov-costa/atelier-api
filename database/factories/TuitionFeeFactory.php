<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Enrollment;
use App\Models\TuitionFee;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<TuitionFee>
 */
class TuitionFeeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'enrollment_id' => Enrollment::factory(),
            'amount'        => fake()->numberBetween(10000, 50000),
            'due_date'      => Carbon::now()->addMonth()->day(10)->toDateString(),
            'paid_at'       => null,
        ];
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes): array => [
            'paid_at' => Carbon::now(),
        ]);
    }
}
