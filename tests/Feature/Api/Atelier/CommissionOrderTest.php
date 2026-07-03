<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Atelier;

use App\Models\CommissionOrder;
use App\Models\Customer;
use App\Models\Piece;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CommissionOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_create_an_order_with_commission_pieces(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $customer = Customer::factory()->create();
        $pieceA = Piece::factory()->create(['price' => 5000, 'production_cost' => 2000]);
        $pieceB = Piece::factory()->create(['price' => 3000, 'production_cost' => 1000]);

        $this->postJson('/api/v1/commission-orders', [
            'customer_id' => $customer->ulid,
            'order_date'  => now()->toDateString(),
            'piece_ids'   => [$pieceA->ulid, $pieceB->ulid],
        ])
            ->assertCreated()
            ->assertJsonPath('data.customer.id', $customer->ulid)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.sale_total', 8000)
            ->assertJsonPath('data.production_cost_total', 3000)
            ->assertJsonPath('data.realized_margin', 5000)
            ->assertJsonPath('data.is_paid', false)
            ->assertJsonCount(2, 'data.pieces');

        $order = CommissionOrder::firstOrFail();
        $this->assertSame($order->id, $pieceA->refresh()->commission_order_id);
        $this->assertSame($order->id, $pieceB->refresh()->commission_order_id);
    }

    public function test_sale_total_override_overrides_derived_sum_and_margin(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $customer = Customer::factory()->create();
        $piece = Piece::factory()->create(['price' => 5000, 'production_cost' => 2000]);

        $this->postJson('/api/v1/commission-orders', [
            'customer_id'         => $customer->ulid,
            'order_date'          => now()->toDateString(),
            'sale_total_override' => 12000,
            'piece_ids'           => [$piece->ulid],
        ])
            ->assertCreated()
            ->assertJsonPath('data.sale_total', 12000)
            ->assertJsonPath('data.production_cost_total', 2000)
            ->assertJsonPath('data.realized_margin', 10000);
    }

    public function test_shipping_adds_to_the_sale_total_and_cost_reduces_the_margin(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $customer = Customer::factory()->create();
        $piece = Piece::factory()->create(['price' => 10000, 'production_cost' => 6000]);

        $this->postJson('/api/v1/commission-orders', [
            'customer_id'      => $customer->ulid,
            'order_date'       => now()->toDateString(),
            'shipping_charged' => 2500,
            'shipping_cost'    => 1500,
            'piece_ids'        => [$piece->ulid],
        ])
            ->assertCreated()
            ->assertJsonPath('data.pieces_total', 10000)
            ->assertJsonPath('data.shipping_charged', 2500)
            ->assertJsonPath('data.sale_total', 12500)
            ->assertJsonPath('data.shipping_cost', 1500)
            ->assertJsonPath('data.realized_margin', 5000);
    }

    public function test_student_piece_is_rejected(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $customer = Customer::factory()->create();
        $student = Piece::factory()->student()->create();

        $this->postJson('/api/v1/commission-orders', [
            'customer_id' => $customer->ulid,
            'order_date'  => now()->toDateString(),
            'piece_ids'   => [$student->ulid],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('piece_ids');
    }

    public function test_updating_piece_ids_syncs_membership(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $customer = Customer::factory()->create();
        $order = CommissionOrder::factory()->for($customer)->create();

        $kept = Piece::factory()->create(['commission_order_id' => $order->id]);
        $removed = Piece::factory()->create(['commission_order_id' => $order->id]);
        $added = Piece::factory()->create();

        $this->putJson("/api/v1/commission-orders/{$order->ulid}", [
            'piece_ids' => [$kept->ulid, $added->ulid],
        ])->assertOk();

        $this->assertSame($order->id, $kept->refresh()->commission_order_id);
        $this->assertSame($order->id, $added->refresh()->commission_order_id);
        $this->assertNull($removed->refresh()->commission_order_id);
    }

    public function test_staff_can_mark_paid_and_transition_status(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $order = CommissionOrder::factory()->create(['status' => 'pending']);

        $this->putJson("/api/v1/commission-orders/{$order->ulid}", [
            'is_paid' => true,
            'status'  => 'delivered',
        ])
            ->assertOk()
            ->assertJsonPath('data.is_paid', true)
            ->assertJsonPath('data.status', 'delivered');

        $this->assertNotNull($order->refresh()->paid_at);

        $this->putJson("/api/v1/commission-orders/{$order->ulid}", ['is_paid' => false])
            ->assertOk()
            ->assertJsonPath('data.is_paid', false);

        $this->assertNull($order->refresh()->paid_at);
    }

    public function test_filters_by_status_and_paid(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        CommissionOrder::factory()->create(['status' => 'pending']);
        CommissionOrder::factory()->paid()->create(['status' => 'delivered']);

        $this->getJson('/api/v1/commission-orders?status=pending')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->getJson('/api/v1/commission-orders?paid=paid')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->getJson('/api/v1/commission-orders?paid=unpaid')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_staff_can_restore_an_order(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $order = CommissionOrder::factory()->create();

        $this->deleteJson("/api/v1/commission-orders/{$order->ulid}")->assertNoContent();
        $this->assertSoftDeleted($order);

        $this->postJson("/api/v1/commission-orders/{$order->ulid}/restore")
            ->assertOk()
            ->assertJsonPath('data.id', $order->ulid);

        $this->assertNotSoftDeleted($order);
    }

    public function test_students_cannot_manage_commission_orders(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/commission-orders')->assertForbidden();
        $this->postJson('/api/v1/commission-orders', [])->assertForbidden();
    }

    public function test_commission_orders_require_authentication(): void
    {
        $this->getJson('/api/v1/commission-orders')->assertUnauthorized();
    }
}
