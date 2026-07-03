<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Enrollment>
 */
class EnrollmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id'                    => User::factory(),
            'annual_fee'                 => fake()->numberBetween(0, 200000),
            'annual_fee_is_paid'         => fake()->boolean(50),
            'is_exempt_from_annual_fee'  => fake()->boolean(10),
            'is_exempt_from_tuition_fee' => fake()->boolean(10),
        ];
    }
}
