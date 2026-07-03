<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Atelier;

use App\Models\Enrollment;
use App\Models\Setting;
use App\Models\TuitionFee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TuitionFeeTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_tuition_fee_derives_the_due_date(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        Setting::current()->update(['tuition_fee_due_day_of_month' => 10]);

        $enrollment = Enrollment::factory()->create(['is_exempt_from_tuition_fee' => false]);

        $expected = now()->addMonthNoOverflow()->day(10)->toDateString();

        $this->postJson('/api/v1/tuition-fees', ['enrollment_id' => $enrollment->ulid])
            ->assertCreated()
            ->assertJsonPath('data.is_paid', false)
            ->assertJsonPath('data.due_date', $expected);
    }

    public function test_the_charged_amount_is_snapshotted_from_settings(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        Setting::current()->update(['tuition_monthly_cost' => 25000]);

        $enrollment = Enrollment::factory()->create(['is_exempt_from_tuition_fee' => false]);

        $this->postJson('/api/v1/tuition-fees', ['enrollment_id' => $enrollment->ulid])
            ->assertCreated()->assertJsonPath('data.amount', 25000);

        Setting::current()->update(['tuition_monthly_cost' => 99999]);
        $this->assertSame(25000, TuitionFee::firstOrFail()->amount);
    }

    public function test_fees_can_be_filtered_by_payment_status(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        TuitionFee::factory()->create();
        TuitionFee::factory()->paid()->create();

        $this->getJson('/api/v1/tuition-fees?status=unpaid')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/tuition-fees?status=paid')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_exempt_enrollment_is_marked_paid_on_creation(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $enrollment = Enrollment::factory()->create(['is_exempt_from_tuition_fee' => true]);

        $this->postJson('/api/v1/tuition-fees', ['enrollment_id' => $enrollment->ulid])
            ->assertCreated()->assertJsonPath('data.is_paid', true);
    }

    public function test_marking_a_tuition_fee_paid_and_unpaid(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $fee = TuitionFee::factory()->create();

        $this->putJson("/api/v1/tuition-fees/{$fee->ulid}", ['is_paid' => true])
            ->assertOk()->assertJsonPath('data.is_paid', true);
        $this->assertNotNull($fee->refresh()->paid_at);

        $this->putJson("/api/v1/tuition-fees/{$fee->ulid}", ['is_paid' => false])
            ->assertOk()->assertJsonPath('data.is_paid', false);
        $this->assertNull($fee->refresh()->paid_at);
    }
}
