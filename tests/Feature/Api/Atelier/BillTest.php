<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Atelier;

use App\Models\Bill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BillTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_crud_bills(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->postJson('/api/v1/bills', [
            'name'            => 'Energia',
            'due_date'        => now()->addDays(10)->toDateString(),
            'is_recurrent'    => false,
            'reference_month' => (int) now()->format('n'),
            'reference_year'  => (int) now()->format('Y'),
            'value'           => 10000,
        ])->assertCreated()->assertJsonPath('data.name', 'Energia');

        $bill = Bill::firstOrFail();

        $this->getJson('/api/v1/bills')->assertOk()->assertJsonStructure(['data' => [['id', 'name', 'value']], 'meta']);
        $this->getJson("/api/v1/bills/{$bill->ulid}")->assertOk()->assertJsonPath('data.id', $bill->ulid);
        $this->putJson("/api/v1/bills/{$bill->ulid}", ['value' => 12345])
            ->assertOk()
            ->assertJsonPath('data.value', 12345);
        $this->deleteJson("/api/v1/bills/{$bill->ulid}")->assertNoContent();

        $this->assertSoftDeleted($bill);
        $this->getJson("/api/v1/bills/{$bill->ulid}")->assertNotFound();
    }

    public function test_due_date_cannot_precede_reference_month(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->postJson('/api/v1/bills', [
            'name'            => 'Atrasada',
            'due_date'        => '2020-01-15',
            'is_recurrent'    => false,
            'reference_month' => 6,
            'reference_year'  => 2026,
            'value'           => 1000,
        ])->assertStatus(422)->assertJsonValidationErrors('due_date');
    }

    public function test_students_cannot_manage_bills(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/bills')->assertForbidden();
        $this->postJson('/api/v1/bills', [])->assertForbidden();
    }

    public function test_bills_require_authentication(): void
    {
        $this->getJson('/api/v1/bills')->assertUnauthorized();
    }
}
