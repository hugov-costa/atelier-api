<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Atelier;

use App\Models\Clay;
use App\Models\ClaySupplier;
use App\Models\Glaze;
use App\Models\GlazeSupplier;
use App\Models\MaterialPurchase;
use App\Models\Piece;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MaterialPurchaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_create_a_clay_purchase_defaulting_supplier(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $supplier = ClaySupplier::factory()->create();
        $clay = Clay::factory()->create(['clay_supplier_id' => $supplier->id, 'price' => 1000]);

        $this->postJson('/api/v1/material-purchases', [
            'material_type'  => 'clay',
            'material_id'    => $clay->ulid,
            'unit_price'     => 1500,
            'quantity'       => 25,
            'total_price'    => 37500,
            'payment_method' => 'pix',
            'purchase_date'  => now()->toDateString(),
        ])
            ->assertCreated()
            ->assertJsonPath('data.material.type', 'clay')
            ->assertJsonPath('data.material.id', $clay->ulid)
            ->assertJsonPath('data.supplier.id', $supplier->ulid)
            ->assertJsonPath('data.is_received', false);

        $purchase = MaterialPurchase::firstOrFail();
        $this->assertSame('clay', $purchase->material_type);
        $this->assertSame($clay->id, $purchase->material_id);
        $this->assertSame($supplier->id, $purchase->supplier_id);

        $this->assertSame(1000, $clay->refresh()->price);
    }

    public function test_freight_is_recorded_and_does_not_affect_the_unit_price(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $clay = Clay::factory()->create(['price' => 1000]);

        $this->postJson('/api/v1/material-purchases', [
            'material_type'  => 'clay',
            'material_id'    => $clay->ulid,
            'unit_price'     => 1500,
            'quantity'       => 25,
            'total_price'    => 40000,
            'freight'        => 2500,
            'payment_method' => 'pix',
            'purchase_date'  => now()->toDateString(),
            'receipt_date'   => now()->toDateString(),
        ])
            ->assertCreated()
            ->assertJsonPath('data.freight', 2500);

        $this->assertSame(1500, $clay->refresh()->price);
    }

    public function test_received_purchase_updates_material_price(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $clay = Clay::factory()->create(['price' => 1000]);

        $this->postJson('/api/v1/material-purchases', [
            'material_type'  => 'clay',
            'material_id'    => $clay->ulid,
            'unit_price'     => 1800,
            'quantity'       => 10,
            'total_price'    => 18000,
            'payment_method' => 'cash',
            'purchase_date'  => now()->toDateString(),
            'receipt_date'   => now()->toDateString(),
        ])->assertCreated()->assertJsonPath('data.is_received', true);

        $this->assertSame(1800, $clay->refresh()->price);
    }

    public function test_marking_a_pending_purchase_received_updates_price(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $clay = Clay::factory()->create(['price' => 1000]);
        $purchase = MaterialPurchase::factory()->create([
            'material_type' => Clay::class,
            'material_id'   => $clay->id,
            'unit_price'    => 2200,
            'receipt_date'  => null,
        ]);

        $this->assertSame(1000, $clay->refresh()->price);

        $this->putJson("/api/v1/material-purchases/{$purchase->ulid}", [
            'receipt_date' => now()->toDateString(),
        ])->assertOk()->assertJsonPath('data.is_received', true);

        $this->assertSame(2200, $clay->refresh()->price);
    }

    public function test_past_piece_snapshot_is_unaffected_by_new_receipt(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $clay = Clay::factory()->create(['price' => 1000]);
        $piece = Piece::factory()->create(['clay_id' => $clay->id, 'clay_unit_price' => 1000, 'price' => 5000]);

        $this->postJson('/api/v1/material-purchases', [
            'material_type'  => 'clay',
            'material_id'    => $clay->ulid,
            'unit_price'     => 9999,
            'quantity'       => 10,
            'total_price'    => 99990,
            'payment_method' => 'pix',
            'purchase_date'  => now()->toDateString(),
            'receipt_date'   => now()->toDateString(),
        ])->assertCreated();

        $this->assertSame(9999, $clay->refresh()->price);
        $this->assertSame(1000, $piece->refresh()->clay_unit_price);
        $this->assertSame(5000, $piece->price);
    }

    public function test_glaze_purchase_works(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $supplier = GlazeSupplier::factory()->create();
        $glaze = Glaze::factory()->create(['glaze_supplier_id' => $supplier->id, 'price' => 2000]);

        $this->postJson('/api/v1/material-purchases', [
            'material_type'  => 'glaze',
            'material_id'    => $glaze->ulid,
            'unit_price'     => 2500,
            'quantity'       => 4,
            'total_price'    => 10000,
            'payment_method' => 'credit_card',
            'purchase_date'  => now()->toDateString(),
            'receipt_date'   => now()->toDateString(),
        ])
            ->assertCreated()
            ->assertJsonPath('data.material.type', 'glaze')
            ->assertJsonPath('data.supplier.type', 'glaze_supplier')
            ->assertJsonPath('data.supplier.id', $supplier->ulid);

        $this->assertSame(2500, $glaze->refresh()->price);
    }

    public function test_invalid_payment_method_is_rejected(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $clay = Clay::factory()->create();

        $this->postJson('/api/v1/material-purchases', [
            'material_type'  => 'clay',
            'material_id'    => $clay->ulid,
            'unit_price'     => 1500,
            'quantity'       => 25,
            'total_price'    => 37500,
            'payment_method' => 'bitcoin',
            'purchase_date'  => now()->toDateString(),
        ])->assertStatus(422)->assertJsonValidationErrors('payment_method');
    }

    public function test_supplier_must_supply_the_material(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $clay = Clay::factory()->create();
        $unrelatedSupplier = ClaySupplier::factory()->create();

        $this->postJson('/api/v1/material-purchases', [
            'material_type'  => 'clay',
            'material_id'    => $clay->ulid,
            'supplier_id'    => $unrelatedSupplier->ulid,
            'unit_price'     => 1500,
            'quantity'       => 10,
            'total_price'    => 15000,
            'payment_method' => 'pix',
            'purchase_date'  => now()->toDateString(),
        ])->assertStatus(422)->assertJsonValidationErrors('supplier_id');
    }

    public function test_material_id_of_wrong_type_is_rejected(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $glaze = Glaze::factory()->create();

        $this->postJson('/api/v1/material-purchases', [
            'material_type'  => 'clay',
            'material_id'    => $glaze->ulid,
            'unit_price'     => 1500,
            'quantity'       => 25,
            'total_price'    => 37500,
            'payment_method' => 'pix',
            'purchase_date'  => now()->toDateString(),
        ])->assertStatus(422)->assertJsonValidationErrors('material_id');
    }

    public function test_filters_by_status_and_material_type(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        MaterialPurchase::factory()->received()->create();
        MaterialPurchase::factory()->create(['receipt_date' => null]);
        MaterialPurchase::factory()->glaze()->create();

        $this->getJson('/api/v1/material-purchases?status=received')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->getJson('/api/v1/material-purchases?status=pending')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->getJson('/api/v1/material-purchases?material_type=glaze')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_staff_can_restore_a_purchase(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $purchase = MaterialPurchase::factory()->create();

        $this->deleteJson("/api/v1/material-purchases/{$purchase->ulid}")->assertNoContent();
        $this->assertSoftDeleted($purchase);

        $this->postJson("/api/v1/material-purchases/{$purchase->ulid}/restore")
            ->assertOk()
            ->assertJsonPath('data.id', $purchase->ulid);

        $this->assertNotSoftDeleted($purchase);
    }

    public function test_students_cannot_manage_material_purchases(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/material-purchases')->assertForbidden();
        $this->postJson('/api/v1/material-purchases', [])->assertForbidden();
    }

    public function test_material_purchases_require_authentication(): void
    {
        $this->getJson('/api/v1/material-purchases')->assertUnauthorized();
    }
}
