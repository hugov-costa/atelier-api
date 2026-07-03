<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\PieceKind;
use App\Models\Setting;
use App\Services\PiecePricingService;
use PHPUnit\Framework\TestCase;

class PiecePricingServiceTest extends TestCase
{
    private function settings(int $baseCost, float $multiplier, float $margin): Setting
    {
        return new Setting([
            'base_cost'              => $baseCost,
            'clay_amount_multiplier' => $multiplier,
            'default_profit_margin'  => $margin,
        ]);
    }

    public function test_production_cost_sums_base_clay_glaze_and_firing(): void
    {
        $result = (new PiecePricingService)->calculate(
            $this->settings(1000, 1.0, 1.0),
            PieceKind::Commission,
            clayPricePerKg: 1000,
            clayAmount: 2.0,
            glazePricePerLiter: 4000,
            glazeAmount: 0.5,
            firingCyclePrices: [500, 300],
            categoryMargin: null,
        );

        // 1000 + (1000*2) + (4000*0.5) + (500+300) = 5800
        $this->assertSame(5800, $result['production_cost']);
        $this->assertSame(5800, $result['price']);
    }

    public function test_category_margin_overrides_the_default(): void
    {
        $result = (new PiecePricingService)->calculate(
            $this->settings(1000, 1.0, 1.0),
            PieceKind::Commission,
            clayPricePerKg: 1000,
            clayAmount: 2.0,
            glazePricePerLiter: null,
            glazeAmount: null,
            firingCyclePrices: [500],
            categoryMargin: 2.0,
        );

        $this->assertSame(3500, $result['production_cost']);
        $this->assertSame(7000, $result['price']);
    }

    public function test_clay_multiplier_applies_once_per_full_kilogram_before_margin(): void
    {
        $result = (new PiecePricingService)->calculate(
            $this->settings(0, 1.5, 1.0),
            PieceKind::Commission,
            clayPricePerKg: 1000,
            clayAmount: 2.0,
            glazePricePerLiter: null,
            glazeAmount: null,
            firingCyclePrices: [],
            categoryMargin: null,
        );

        // production_cost = 2000; multiplier = 1.5^2 = 2.25; price = 4500
        $this->assertSame(2000, $result['production_cost']);
        $this->assertSame(4500, $result['price']);
    }

    public function test_student_pieces_are_charged_materials_and_firing_without_base_or_margin(): void
    {
        $result = (new PiecePricingService)->calculate(
            // A non-zero base cost and a doubling margin must both be ignored for students.
            $this->settings(1000, 1.5, 2.0),
            PieceKind::Student,
            clayPricePerKg: 1000,
            clayAmount: 2.0,
            glazePricePerLiter: 4000,
            glazeAmount: 0.5,
            firingCyclePrices: [500, 300],
            categoryMargin: 3.0,
        );

        // materials + firing = (1000*2) + (4000*0.5) + (500+300) = 4800; price == cost.
        $this->assertSame(4800, $result['production_cost']);
        $this->assertSame(4800, $result['price']);
    }
}
