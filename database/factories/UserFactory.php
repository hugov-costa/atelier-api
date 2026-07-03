<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name'              => fake()->name(),
            'email'             => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password'          => static::$password ??= Hash::make('password'),
            'role'              => UserRole::User->value,
            'is_active'         => true,
            'admission_date'    => now()->subMonths(fake()->numberBetween(0, 36))->toDateString(),
            'birthday'          => now()->subYears(fake()->numberBetween(16, 70))->toDateString(),
            'phone'             => (string) fake()->numerify('119########'),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the user is an administrator (read access to other users).
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::Admin->value,
        ]);
    }

    /**
     * Indicate that the user is a master (full management of other users).
     */
    public function master(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::Master->value,
        ]);
    }
}
