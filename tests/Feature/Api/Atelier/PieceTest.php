<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Atelier;

use App\Models\Clay;
use App\Models\FiringCycle;
use App\Models\Glaze;
use App\Models\Piece;
use App\Models\PieceCategory;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PieceTest extends TestCase
{
    use RefreshDatabase;

    public function test_commission_price_and_production_cost_are_computed_server_side(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        Setting::current()->update([
            'base_cost'              => 1000,
            'clay_amount_multiplier' => 1,
            'default_profit_margin'  => 1,
        ]);

        $clay = Clay::factory()->create(['price' => 1000]);
        $category = PieceCategory::factory()->create(['profit_margin' => 2, 'available_until' => null]);
        $cycle = FiringCycle::factory()->create(['price_per_unit' => 500]);
        $student = User::factory()->create();

        $this->postJson('/api/v1/pieces', [
            'kind'              => 'commission',
            'clay_id'           => $clay->ulid,
            'clay_amount'       => 2,
            'piece_category_id' => $category->ulid,
            'user_id'           => $student->ulid,
            'name'              => 'Tigela',
            'firing_cycle_ids'  => [$cycle->ulid],
            'price'             => 999999,
        ])
            ->assertCreated()
            ->assertJsonPath('data.kind', 'commission')
            ->assertJsonPath('data.production_cost', 3500)
            ->assertJsonPath('data.price', 7000)
            ->assertJsonPath('data.base_cost', 1000)
            ->assertJsonCount(1, 'data.firing_cycles');
    }

    public function test_student_pieces_exclude_base_cost_and_margin(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        Setting::current()->update([
            'base_cost'              => 1000,
            'clay_amount_multiplier' => 1,
            'default_profit_margin'  => 2,
        ]);

        $clay = Clay::factory()->create(['price' => 1000]);
        $glaze = Glaze::factory()->create(['price' => 4000]);
        $cycle = FiringCycle::factory()->create(['price_per_unit' => 500]);
        $student = User::factory()->create();

        $this->postJson('/api/v1/pieces', [
            'kind'             => 'student',
            'clay_id'          => $clay->ulid,
            'clay_amount'      => 2,
            'glaze_id'         => $glaze->ulid,
            'glaze_amount'     => 0.5,
            'user_id'          => $student->ulid,
            'name'             => 'Caneca da aula',
            'firing_cycle_ids' => [$cycle->ulid],
        ])
            ->assertCreated()
            ->assertJsonPath('data.kind', 'student')
            ->assertJsonPath('data.production_cost', 4500)
            ->assertJsonPath('data.price', 4500)
            ->assertJsonPath('data.base_cost', 0)
            ->assertJsonPath('data.profit_margin', null);
    }

    public function test_pricing_inputs_are_snapshotted_and_survive_later_price_changes(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        Setting::current()->update(['base_cost' => 0, 'clay_amount_multiplier' => 1, 'default_profit_margin' => 1]);

        $clay = Clay::factory()->create(['price' => 1000]);
        $student = User::factory()->create();

        $this->postJson('/api/v1/pieces', [
            'kind'        => 'student',
            'clay_id'     => $clay->ulid,
            'clay_amount' => 1,
            'user_id'     => $student->ulid,
            'name'        => 'Caneca',
        ])
            ->assertCreated()
            ->assertJsonPath('data.price', 1000)
            ->assertJsonPath('data.clay_unit_price', 1000);

        $piece = Piece::firstOrFail();

        $clay->update(['price' => 2000]);

        $this->assertSame(1000, $piece->refresh()->price);
        $this->assertSame(1000, $piece->clay_unit_price);
    }

    public function test_update_only_changes_administrative_fields(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $piece = Piece::factory()->create(['name' => 'Antigo', 'price' => 5000, 'production_cost' => 5000]);
        $otherClay = Clay::factory()->create(['price' => 99999]);

        $this->putJson("/api/v1/pieces/{$piece->ulid}", [
            'name'        => 'Renomeado',
            'clay_id'     => $otherClay->ulid,
            'clay_amount' => 500,
            'price'       => 1,
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Renomeado')
            ->assertJsonPath('data.price', 5000);

        $piece->refresh();
        $this->assertSame(5000, $piece->price);
        $this->assertNotSame($otherClay->id, $piece->clay_id);
    }

    public function test_the_pricing_snapshot_is_immutable_at_the_model_layer(): void
    {
        $piece = Piece::factory()->create(['price' => 5000]);

        $this->expectException(\LogicException::class);

        $piece->update(['price' => 1]);
    }

    public function test_students_cannot_manage_pieces(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/pieces')->assertForbidden();
    }
}
