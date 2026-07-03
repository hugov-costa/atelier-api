<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Atelier;

use App\Models\Clay;
use App\Models\ClaySupplier;
use App\Models\GlazeSupplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MaterialTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_crud_clay_suppliers_and_clays(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->postJson('/api/v1/clay-suppliers', [
            'email' => 'fornecedor@argila.com',
            'name'  => 'Argilas do Vale',
            'phone' => '11987654321',
        ])->assertCreated();

        $supplier = ClaySupplier::firstOrFail();

        $this->postJson('/api/v1/clays', [
            'clay_supplier_id' => $supplier->ulid,
            'name'             => 'Grés branco',
            'price'            => 1200,
        ])->assertCreated()->assertJsonPath('data.clay_supplier_id', $supplier->ulid);

        $this->getJson('/api/v1/clays')
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'clay_supplier_id', 'price']]]);
    }

    public function test_clay_requires_an_existing_supplier(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->postJson('/api/v1/clays', [
            'clay_supplier_id' => 'non-existent-ulid',
            'name'             => 'Grés',
            'price'            => 100,
        ])->assertStatus(422)->assertJsonValidationErrors('clay_supplier_id');
    }

    public function test_clay_rejects_a_soft_deleted_supplier(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $supplier = ClaySupplier::factory()->create();
        $supplier->delete();

        $this->postJson('/api/v1/clays', [
            'clay_supplier_id' => $supplier->ulid,
            'name'             => 'Grés',
            'price'            => 1000,
        ])->assertStatus(422)->assertJsonValidationErrors('clay_supplier_id');
    }

    public function test_staff_can_crud_glaze_suppliers_and_glazes(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $supplier = GlazeSupplier::factory()->create();

        $this->postJson('/api/v1/glazes', [
            'glaze_supplier_id' => $supplier->ulid,
            'name'              => 'Azul cobalto',
            'price'             => 4500,
        ])->assertCreated()->assertJsonPath('data.glaze_supplier_id', $supplier->ulid);

        $this->getJson('/api/v1/glaze-suppliers')->assertOk();
        $this->getJson('/api/v1/glazes')->assertOk();
    }

    public function test_soft_deleting_a_supplier_cascades_to_its_clays(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $supplier = ClaySupplier::factory()->create();
        $clay = Clay::factory()->for($supplier)->create();

        $this->deleteJson("/api/v1/clay-suppliers/{$supplier->ulid}")->assertNoContent();

        $this->assertSoftDeleted($supplier);
        $this->assertSoftDeleted($clay);
        $this->getJson("/api/v1/clays/{$clay->ulid}")->assertNotFound();
    }

    public function test_a_soft_deleted_suppliers_email_and_name_can_be_reused(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $supplier = ClaySupplier::factory()->create(['email' => 'reuse@argila.com', 'name' => 'Argilas Reuso']);
        $supplier->delete();

        $this->postJson('/api/v1/clay-suppliers', [
            'email' => 'reuse@argila.com',
            'name'  => 'Argilas Reuso',
            'phone' => '11987654321',
        ])->assertCreated();

        $this->assertSame(1, ClaySupplier::query()->where('email', 'reuse@argila.com')->count());
    }

    public function test_students_cannot_manage_materials(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/clays')->assertForbidden();
        $this->getJson('/api/v1/glazes')->assertForbidden();
    }
}
