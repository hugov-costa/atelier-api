<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Bill;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Bill>
 */
class BillFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $now = Carbon::now();

        return [
            'description'     => fake()->optional()->sentence(),
            'due_date'        => $now->copy()->addDays(fake()->numberBetween(1, 30))->toDateString(),
            'is_recurrent'    => fake()->boolean(20),
            'name'            => fake()->words(3, true),
            'reference_month' => (int) $now->format('n'),
            'reference_year'  => (int) $now->format('Y'),
            'value'           => fake()->numberBetween(1000, 500000),
        ];
    }
}
