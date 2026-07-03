<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PieceKind;
use App\Models\Setting;

/**
 * Pure calculator for a piece's production cost and selling price. Kept free of
 * persistence so the pricing rule is trivially unit-testable and lives in one
 * place.
 *
 * NOTE: the ceramics-atelier source repository ships these tables without any
 * pricing implementation, so the formula below is a deliberate, documented
 * interpretation of the column comments — not a port of existing behaviour.
 * With the seeded defaults (clay multiplier 1.0, profit margin 1.0) it reduces
 * to `price == production_cost`, so it is a safe no-op until an operator tunes
 * the settings. Revisit the model if the atelier's real costing differs.
 */
final class PiecePricingService
{
    /**
     * All monetary inputs and outputs are integer cents.
     *
     * Student pieces are the student's own class work: the studio overhead is
     * already covered by their tuition, so they are charged only for the
     * materials and firing consumed (no base cost, no margin, no multiplier):
     *
     *   student:     production_cost = price = materials + sum(firing_cycle_prices)
     *
     * Commission pieces are made by the atelier and sold, so they carry the base
     * cost and a profit margin:
     *
     *   commission:  production_cost = base_cost + materials + sum(firing_cycle_prices)
     *                clay_multiplier  = clay_amount_multiplier ^ floor(clay_amount_kg) // one factor per full kg
     *                margin           = category_profit_margin ?? default_profit_margin
     *                price            = round(production_cost * clay_multiplier * margin)
     *
     * where `materials = round(clay_price_per_kg * clay_amount_kg) + round(glaze_price_per_l * glaze_amount_l)`
     * (the glaze term is 0 when no glaze is used).
     *
     * @param  array<int, int>  $firingCyclePrices
     * @return array{production_cost: int, price: int}
     */
    public function calculate(
        Setting $settings,
        PieceKind $kind,
        int $clayPricePerKg,
        float $clayAmount,
        ?int $glazePricePerLiter,
        ?float $glazeAmount,
        array $firingCyclePrices,
        ?float $categoryMargin,
    ): array {
        $clayCost = (int) round($clayPricePerKg * $clayAmount);
        $glazeCost = $glazePricePerLiter !== null && $glazeAmount !== null
            ? (int) round($glazePricePerLiter * $glazeAmount)
            : 0;
        $materials = $clayCost + $glazeCost + array_sum($firingCyclePrices);

        if ($kind === PieceKind::Student) {
            return ['production_cost' => $materials, 'price' => $materials];
        }

        $productionCost = $settings->base_cost + $materials;

        $fullKilograms = max(0, (int) floor($clayAmount));
        $multiplier = $fullKilograms > 0 ? $settings->clay_amount_multiplier ** $fullKilograms : 1.0;
        $margin = $categoryMargin ?? $settings->default_profit_margin;

        $price = (int) round($productionCost * $multiplier * $margin);

        return ['production_cost' => $productionCost, 'price' => $price];
    }
}
