<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Atelier;

use App\Models\CommissionOrder;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CustomerTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_create_a_customer(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->postJson('/api/v1/customers', [
            'name'        => 'Maria Oliveira',
            'email'       => 'maria@example.com',
            'phone'       => '11987654321',
            'description' => 'Cliente recorrente.',
        ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Maria Oliveira')
            ->assertJsonPath('data.email', 'maria@example.com');

        $this->assertDatabaseHas('customers', ['name' => 'Maria Oliveira']);
    }

    public function test_name_is_required(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->postJson('/api/v1/customers', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('name');
    }

    public function test_staff_can_update_a_customer(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $customer = Customer::factory()->create(['name' => 'Old Name']);

        $this->putJson("/api/v1/customers/{$customer->ulid}", ['name' => 'New Name'])
            ->assertOk()
            ->assertJsonPath('data.name', 'New Name');

        $this->assertSame('New Name', $customer->refresh()->name);
    }

    public function test_staff_can_list_customers(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        Customer::factory()->count(3)->create();

        $this->getJson('/api/v1/customers')
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_staff_can_restore_a_customer(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $customer = Customer::factory()->create();

        $this->deleteJson("/api/v1/customers/{$customer->ulid}")->assertNoContent();
        $this->assertSoftDeleted($customer);

        $this->postJson("/api/v1/customers/{$customer->ulid}/restore")
            ->assertOk()
            ->assertJsonPath('data.id', $customer->ulid);

        $this->assertNotSoftDeleted($customer);
    }

    public function test_deleting_a_customer_cascades_to_orders_and_restore_brings_them_back(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $customer = Customer::factory()->create();
        $order = CommissionOrder::factory()->for($customer)->create();

        $this->deleteJson("/api/v1/customers/{$customer->ulid}")->assertNoContent();

        $this->assertSoftDeleted($customer);
        $this->assertSoftDeleted($order);

        $this->postJson("/api/v1/customers/{$customer->ulid}/restore")->assertOk();

        $this->assertNotSoftDeleted($customer);
        $this->assertNotSoftDeleted($order);
    }

    public function test_students_cannot_manage_customers(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/customers')->assertForbidden();
        $this->postJson('/api/v1/customers', [])->assertForbidden();
    }

    public function test_customers_require_authentication(): void
    {
        $this->getJson('/api/v1/customers')->assertUnauthorized();
    }
}
