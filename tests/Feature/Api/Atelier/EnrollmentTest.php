<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Atelier;

use App\Models\Enrollment;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EnrollmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_annual_fee_is_derived_from_settings(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        Setting::current()->update(['annual_enrollment_cost' => 15000]);

        $student = User::factory()->create();

        $this->postJson('/api/v1/enrollments', ['user_id' => $student->ulid])
            ->assertCreated()
            ->assertJsonPath('data.annual_fee', 15000)
            ->assertJsonPath('data.user_id', $student->ulid);
    }

    public function test_exempt_enrollment_has_no_annual_fee(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        Setting::current()->update(['annual_enrollment_cost' => 15000]);

        $student = User::factory()->create();

        $this->postJson('/api/v1/enrollments', [
            'user_id'                   => $student->ulid,
            'is_exempt_from_annual_fee' => true,
        ])->assertCreated()->assertJsonPath('data.annual_fee', 0);
    }

    public function test_becoming_exempt_zeroes_the_annual_fee(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $enrollment = Enrollment::factory()->create(['annual_fee' => 15000, 'is_exempt_from_annual_fee' => false]);

        $this->putJson("/api/v1/enrollments/{$enrollment->ulid}", ['is_exempt_from_annual_fee' => true])
            ->assertOk()->assertJsonPath('data.annual_fee', 0);
    }

    public function test_paying_the_annual_fee_stamps_and_clears_the_payment_date(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $enrollment = Enrollment::factory()->create(['annual_fee' => 15000, 'annual_fee_is_paid' => false]);
        $this->assertNull($enrollment->annual_fee_paid_at);

        $this->putJson("/api/v1/enrollments/{$enrollment->ulid}", ['annual_fee_is_paid' => true])
            ->assertOk()->assertJsonPath('data.annual_fee_is_paid', true);
        $this->assertNotNull($enrollment->refresh()->annual_fee_paid_at);

        $this->putJson("/api/v1/enrollments/{$enrollment->ulid}", ['annual_fee_is_paid' => false])
            ->assertOk()->assertJsonPath('data.annual_fee_is_paid', false);
        $this->assertNull($enrollment->refresh()->annual_fee_paid_at);
    }

    public function test_an_inactive_user_cannot_be_enrolled(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $inactive = User::factory()->create(['is_active' => false]);

        $this->postJson('/api/v1/enrollments', ['user_id' => $inactive->ulid])
            ->assertStatus(422)->assertJsonValidationErrors('user_id');
    }

    public function test_enrollments_can_be_filtered_by_user(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $student = User::factory()->create();
        Enrollment::factory()->for($student)->create();
        Enrollment::factory()->create();

        $this->getJson('/api/v1/enrollments?user_id='.$student->ulid)
            ->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_students_cannot_manage_enrollments(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/enrollments')->assertForbidden();
    }
}
