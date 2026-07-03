<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Atelier;

use App\Models\Clay;
use App\Models\Enrollment;
use App\Models\Piece;
use App\Models\PieceCharge;
use App\Models\Setting;
use App\Models\TuitionFee;
use App\Models\User;
use App\Notifications\BillingStatementNotification;
use App\Services\TuitionFeeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PieceChargeTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_student_piece_raises_a_charge_and_a_commission_does_not(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        Setting::current()->update(['base_cost' => 1000, 'clay_amount_multiplier' => 1, 'default_profit_margin' => 1]);

        $clay = Clay::factory()->create(['price' => 1000]);
        $student = User::factory()->create();

        $this->postJson('/api/v1/pieces', [
            'kind'        => 'student',
            'clay_id'     => $clay->ulid,
            'clay_amount' => 2,
            'user_id'     => $student->ulid,
            'name'        => 'Caneca',
        ])->assertCreated();

        $piece = Piece::firstOrFail();
        $charge = PieceCharge::firstOrFail();
        $this->assertSame($piece->id, $charge->piece_id);
        $this->assertSame($student->id, $charge->user_id);
        $this->assertSame(2000, $charge->amount);
        $this->assertSame($piece->price, $charge->amount);

        $this->postJson('/api/v1/pieces', [
            'kind'        => 'commission',
            'clay_id'     => $clay->ulid,
            'clay_amount' => 1,
            'user_id'     => $student->ulid,
            'name'        => 'Vaso',
        ])->assertCreated();

        $this->assertSame(1, PieceCharge::count());
    }

    public function test_paying_the_tuition_settles_and_unsettling_reverses_the_piece_charge(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $student = User::factory()->create();
        $enrollment = Enrollment::factory()->for($student)->create();
        $piece = Piece::factory()->student()->for($student)->create();
        $charge = PieceCharge::factory()->create([
            'piece_id' => $piece->id, 'user_id' => $student->id, 'due_date' => '2026-02-10', 'paid_at' => null,
        ]);
        $tuition = TuitionFee::factory()->for($enrollment)->create(['due_date' => '2026-02-10', 'paid_at' => null]);

        $this->putJson("/api/v1/tuition-fees/{$tuition->ulid}", ['is_paid' => true])->assertOk();
        $this->assertNotNull($charge->refresh()->paid_at);

        $this->putJson("/api/v1/tuition-fees/{$tuition->ulid}", ['is_paid' => false])->assertOk();
        $this->assertNull($charge->refresh()->paid_at);
    }

    public function test_end_to_end_a_student_piece_is_billed_linked_and_settled_across_the_cycle(): void
    {
        $this->travelTo(Carbon::parse('2026-01-05'));
        Notification::fake();

        Sanctum::actingAs(User::factory()->admin()->create());
        Setting::current()->update([
            'tuition_fee_due_day_of_month'    => 10,
            'piece_charge_billing_grace_days' => 20,
            'base_cost'                       => 1000,
            'clay_amount_multiplier'          => 1,
            'default_profit_margin'           => 1,
            'tuition_monthly_cost'            => 15000,
        ]);

        $student = User::factory()->create(['is_active' => true]);
        Enrollment::factory()->for($student)->create([
            'is_exempt_from_tuition_fee'   => false,
            'is_exempt_from_piece_charges' => false,
        ]);
        $clay = Clay::factory()->create(['price' => 1000]);

        $this->postJson('/api/v1/pieces', [
            'kind'        => 'student',
            'clay_id'     => $clay->ulid,
            'clay_amount' => 2,
            'user_id'     => $student->ulid,
            'name'        => 'Caneca',
        ])->assertCreated();

        $charge = PieceCharge::firstOrFail();
        $this->assertSame('2026-02-10', $charge->due_date->toDateString());
        $this->assertNull($charge->tuition_fee_id);

        app(TuitionFeeService::class)->generateForMonth(Carbon::parse('2026-02-01'));

        $tuition = TuitionFee::firstOrFail();
        $this->assertSame('2026-02-10', $tuition->due_date->toDateString());
        $this->assertSame($tuition->id, $charge->refresh()->tuition_fee_id);

        $this->putJson("/api/v1/tuition-fees/{$tuition->ulid}", ['is_paid' => true])->assertOk();
        $this->assertNotNull($charge->refresh()->paid_at);

        Notification::assertSentTo($student, BillingStatementNotification::class);
    }

    public function test_unsettling_a_tuition_does_not_reverse_a_hand_settled_charge_on_the_same_date(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $student = User::factory()->create();
        $enrollment = Enrollment::factory()->for($student)->create();

        $handSettled = PieceCharge::factory()->paid()->create([
            'user_id' => $student->id, 'due_date' => '2026-02-10',
        ]);
        $this->assertNull($handSettled->tuition_fee_id);

        $tuition = TuitionFee::factory()->for($enrollment)->create(['due_date' => '2026-02-10', 'paid_at' => null]);

        $this->putJson("/api/v1/tuition-fees/{$tuition->ulid}", ['is_paid' => true])->assertOk();
        $this->putJson("/api/v1/tuition-fees/{$tuition->ulid}", ['is_paid' => false])->assertOk();

        $this->assertNotNull($handSettled->refresh()->paid_at);
        $this->assertNull($handSettled->tuition_fee_id);
    }

    public function test_deactivating_a_student_pulls_open_charges_to_the_next_cycle(): void
    {
        $this->travelTo(Carbon::parse('2026-01-05'));
        Sanctum::actingAs(User::factory()->master()->create());
        Setting::current()->update(['tuition_fee_due_day_of_month' => 10]);

        $student = User::factory()->create(['is_active' => true]);
        $far = PieceCharge::factory()->create([
            'user_id'  => $student->id,
            'due_date' => '2026-03-10',
            'paid_at'  => null,
        ]);
        $paid = PieceCharge::factory()->paid()->create(['user_id' => $student->id, 'due_date' => '2026-03-10']);

        $this->patchJson("/api/v1/users/{$student->ulid}", ['is_active' => false])->assertOk();

        $this->assertSame('2026-01-10', $far->refresh()->due_date->toDateString());
        $this->assertSame('2026-03-10', $paid->refresh()->due_date->toDateString());
    }

    public function test_report_counts_paid_piece_charges_as_revenue(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $year = (int) now()->format('Y');
        $month = (int) now()->format('n');

        PieceCharge::factory()->create(['amount' => 12000, 'paid_at' => now()]);
        PieceCharge::factory()->create(['amount' => 5000, 'paid_at' => null]);

        $this->getJson("/api/v1/reports/monthly?year={$year}&month={$month}")
            ->assertOk()
            ->assertJsonPath('data.revenue.piece_charges_paid', 12000);
    }

    public function test_deleting_a_piece_cascades_to_its_charge_and_restoring_brings_it_back(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $piece = Piece::factory()->student()->create();
        $charge = PieceCharge::factory()->create(['piece_id' => $piece->id, 'user_id' => $piece->user_id]);

        $this->deleteJson("/api/v1/pieces/{$piece->ulid}")->assertNoContent();
        $this->assertSoftDeleted($charge);

        $this->postJson("/api/v1/pieces/{$piece->ulid}/restore")->assertOk();
        $this->assertNotSoftDeleted($charge);
    }

    public function test_staff_can_manually_mark_a_charge_paid_and_unpaid(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $charge = PieceCharge::factory()->create(['paid_at' => null]);

        $this->putJson("/api/v1/piece-charges/{$charge->ulid}", ['is_paid' => true])
            ->assertOk()->assertJsonPath('data.is_paid', true);
        $this->assertNotNull($charge->refresh()->paid_at);

        $this->putJson("/api/v1/piece-charges/{$charge->ulid}", ['is_paid' => false])
            ->assertOk()->assertJsonPath('data.is_paid', false);
        $this->assertNull($charge->refresh()->paid_at);
    }

    public function test_students_cannot_mark_a_charge_paid(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $charge = PieceCharge::factory()->create();

        $this->putJson("/api/v1/piece-charges/{$charge->ulid}", ['is_paid' => true])->assertForbidden();
    }

    public function test_staff_can_list_piece_charges_and_students_cannot(): void
    {
        PieceCharge::factory()->count(2)->create();

        Sanctum::actingAs(User::factory()->admin()->create());
        $this->getJson('/api/v1/piece-charges')->assertOk()->assertJsonCount(2, 'data');

        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/v1/piece-charges')->assertForbidden();
    }
}
