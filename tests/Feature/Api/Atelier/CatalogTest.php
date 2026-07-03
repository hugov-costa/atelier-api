<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Atelier;

use App\Models\FiringCycle;
use App\Models\PieceCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_crud_piece_categories(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->postJson('/api/v1/piece-categories', [
            'name'          => 'Coleção de Natal',
            'profit_margin' => 2.5,
        ])->assertCreated()->assertJsonPath('data.is_available', true);

        $category = PieceCategory::firstOrFail();

        $this->putJson("/api/v1/piece-categories/{$category->ulid}", ['profit_margin' => 3])
            ->assertOk()->assertJsonPath('data.profit_margin', 3);
        $this->deleteJson("/api/v1/piece-categories/{$category->ulid}")->assertNoContent();
    }

    public function test_staff_can_crud_firing_cycles(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->postJson('/api/v1/firing-cycles', [
            'cycle'          => 1,
            'duration'       => 480,
            'name'           => 'Biscoito',
            'price_per_unit' => 800,
            'temperature'    => 1000,
        ])->assertCreated();

        $cycle = FiringCycle::firstOrFail();

        $this->getJson("/api/v1/firing-cycles/{$cycle->ulid}")->assertOk()->assertJsonPath('data.price_per_unit', 800);
    }

    public function test_students_cannot_access_catalog(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/piece-categories')->assertForbidden();
        $this->getJson('/api/v1/firing-cycles')->assertForbidden();
    }
}
