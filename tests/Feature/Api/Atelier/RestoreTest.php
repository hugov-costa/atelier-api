<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Atelier;

use App\Models\Clay;
use App\Models\ClaySupplier;
use App\Models\Enrollment;
use App\Models\Piece;
use App\Models\TuitionFee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RestoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_restore_a_soft_deleted_piece(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $piece = Piece::factory()->create();
        $piece->delete();

        $this->postJson("/api/v1/pieces/{$piece->ulid}/restore")
            ->assertOk()
            ->assertJsonPath('data.id', $piece->ulid);

        $this->assertNotSoftDeleted($piece);
    }

    public function test_restoring_a_clay_supplier_cascades_to_its_clays(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $supplier = ClaySupplier::factory()->create();
        $clay = Clay::factory()->for($supplier)->create();

        $supplier->delete();

        $this->assertSoftDeleted($supplier);
        $this->assertSoftDeleted($clay);

        $this->postJson("/api/v1/clay-suppliers/{$supplier->ulid}/restore")
            ->assertOk();

        $this->assertNotSoftDeleted($supplier);
        $this->assertNotSoftDeleted($clay);
    }

    public function test_restoring_an_enrollment_cascades_to_its_tuition_fees(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $enrollment = Enrollment::factory()->create();
        $fee = TuitionFee::factory()->for($enrollment)->create();

        $enrollment->delete();

        $this->assertSoftDeleted($enrollment);
        $this->assertSoftDeleted($fee);

        $this->postJson("/api/v1/enrollments/{$enrollment->ulid}/restore")
            ->assertOk();

        $this->assertNotSoftDeleted($enrollment);
        $this->assertNotSoftDeleted($fee);
    }

    public function test_non_staff_users_cannot_restore_a_resource(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $piece = Piece::factory()->create();
        $piece->delete();

        $this->postJson("/api/v1/pieces/{$piece->ulid}/restore")
            ->assertForbidden();

        $this->assertSoftDeleted($piece);
    }
}
