<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Models\Clay;
use App\Models\ClaySupplier;
use App\Models\Glaze;
use App\Models\GlazeSupplier;
use App\Models\MaterialPurchase;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<MaterialPurchase>
 */
class MaterialPurchaseFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $unitPrice = fake()->numberBetween(500, 5000);
        $quantity = fake()->randomFloat(3, 1, 50);

        return [
            'material_id'    => Clay::factory(),
            'supplier_id'    => ClaySupplier::factory(),
            'description'    => fake()->optional()->sentence(),
            'invoice_number' => fake()->optional()->numerify('NF-######'),
            'lot'            => fake()->optional()->bothify('LOTE-####'),
            'material_type'  => (new Clay)->getMorphClass(),
            'payment_method' => fake()->randomElement(PaymentMethod::cases()),
            'purchase_date'  => Carbon::now()->subDays(fake()->numberBetween(0, 60))->toDateString(),
            'quantity'       => $quantity,
            'receipt_date'   => null,
            'supplier_type'  => (new ClaySupplier)->getMorphClass(),
            'total_price'    => (int) round($unitPrice * $quantity),
            'unit_price'     => $unitPrice,
        ];
    }

    /**
     * A purchase whose goods have physically arrived.
     */
    public function received(): self
    {
        return $this->state(fn (): array => [
            'receipt_date' => Carbon::now()->toDateString(),
        ]);
    }

    /**
     * A glaze purchase (points the polymorphic relations at Glaze/GlazeSupplier).
     */
    public function glaze(): self
    {
        return $this->state(fn (): array => [
            'material_type' => (new Glaze)->getMorphClass(),
            'material_id'   => Glaze::factory(),
            'supplier_type' => (new GlazeSupplier)->getMorphClass(),
            'supplier_id'   => GlazeSupplier::factory(),
        ]);
    }
}
