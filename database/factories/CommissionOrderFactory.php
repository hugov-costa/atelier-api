<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\CommissionOrder;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<CommissionOrder>
 */
class CommissionOrderFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id'         => Customer::factory(),
            'delivery_date'       => null,
            'description'         => fake()->optional()->sentence(),
            'order_date'          => Carbon::now()->subDays(fake()->numberBetween(0, 60))->toDateString(),
            'paid_at'             => null,
            'sale_total_override' => null,
            'status'              => fake()->randomElement(OrderStatus::cases()),
        ];
    }

    /**
     * An order that has been paid.
     */
    public function paid(): self
    {
        return $this->state(fn (): array => [
            'paid_at' => Carbon::now(),
        ]);
    }
}
