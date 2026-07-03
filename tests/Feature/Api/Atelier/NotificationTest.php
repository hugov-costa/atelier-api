<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Atelier;

use App\Models\Clay;
use App\Models\Enrollment;
use App\Models\PieceCharge;
use App\Models\Setting;
use App\Models\TuitionFee;
use App\Models\User;
use App\Notifications\AnnualFeeDue;
use App\Notifications\BillingStatementNotification;
use App\Notifications\EnrollmentCreated;
use App\Notifications\PieceChargeCreated;
use App\Services\StatementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_an_enrollment_notifies_the_student(): void
    {
        Notification::fake();
        Sanctum::actingAs(User::factory()->admin()->create());

        $student = User::factory()->create();

        $this->postJson('/api/v1/enrollments', ['user_id' => $student->ulid])->assertCreated();

        Notification::assertSentTo($student, EnrollmentCreated::class);
    }

    public function test_creating_a_tuition_fee_notifies_the_student(): void
    {
        Notification::fake();
        Sanctum::actingAs(User::factory()->admin()->create());

        $student = User::factory()->create();
        $enrollment = Enrollment::factory()->for($student)->create(['is_exempt_from_tuition_fee' => false]);

        Setting::query()->update(['tuition_monthly_cost' => 20000]);

        $this->postJson('/api/v1/tuition-fees', ['enrollment_id' => $enrollment->ulid])->assertCreated();

        Notification::assertSentTo($student, BillingStatementNotification::class);
    }

    public function test_creating_a_student_piece_notifies_the_student(): void
    {
        Notification::fake();
        Sanctum::actingAs(User::factory()->admin()->create());

        $clay = Clay::factory()->create(['price' => 1000]);
        $student = User::factory()->create();

        $this->postJson('/api/v1/pieces', [
            'kind'        => 'student',
            'clay_id'     => $clay->ulid,
            'clay_amount' => 1,
            'user_id'     => $student->ulid,
            'name'        => 'Caneca',
        ])->assertCreated();

        Notification::assertSentTo($student, PieceChargeCreated::class);
    }

    public function test_creating_a_commission_piece_sends_no_piece_charge_notification(): void
    {
        Notification::fake();
        Sanctum::actingAs(User::factory()->admin()->create());

        $clay = Clay::factory()->create(['price' => 1000]);
        $customerOwner = User::factory()->create();

        $this->postJson('/api/v1/pieces', [
            'kind'        => 'commission',
            'clay_id'     => $clay->ulid,
            'clay_amount' => 1,
            'user_id'     => $customerOwner->ulid,
            'name'        => 'Vaso',
        ])->assertCreated();

        Notification::assertNotSentTo($customerOwner, PieceChargeCreated::class);
    }

    public function test_channel_matrix(): void
    {
        $user = User::factory()->make();
        $enrollment = Enrollment::factory()->make();
        $charge = PieceCharge::factory()->make();

        $statement = [
            'student_name'      => 'Aluno',
            'enrollment_number' => null,
            'reference_month'   => 'Março de 2026',
            'due_date'          => '2026-03-01',
            'tuition_amount'    => 20000,
            'pieces'            => [],
            'total'             => 20000,
            'logo_data_uri'     => null,
        ];

        $this->assertSame(['mail', 'database'], (new EnrollmentCreated($enrollment))->via($user));
        $this->assertSame(
            ['mail', 'database'],
            (new BillingStatementNotification($statement, 'generated'))->via($user)
        );
        $this->assertSame(['mail', 'database'], (new AnnualFeeDue($enrollment, false))->via($user));
        $this->assertSame(['database'], (new PieceChargeCreated($charge))->via($user));
    }

    public function test_reminder_command_notifies_upcoming_and_overdue_tuition(): void
    {
        Notification::fake();

        $student = User::factory()->create(['is_active' => true]);
        $enrollment = Enrollment::factory()->for($student)->create();

        TuitionFee::factory()->for($enrollment)->create([
            'amount'   => 20000,
            'due_date' => Carbon::today()->addDays(5)->toDateString(),
            'paid_at'  => null,
        ]);
        TuitionFee::factory()->for($enrollment)->create([
            'amount'   => 20000,
            'due_date' => Carbon::today()->subDay()->toDateString(),
            'paid_at'  => null,
        ]);

        Artisan::call('notifications:send-billing-reminders');

        Notification::assertSentToTimes($student, BillingStatementNotification::class, 2);
    }

    public function test_reminder_command_notifies_upcoming_annual_fee(): void
    {
        Notification::fake();

        $student = User::factory()->create(['is_active' => true]);
        Enrollment::factory()->for($student)->create([
            'annual_fee'          => 15000,
            'annual_fee_is_paid'  => false,
            'annual_fee_due_date' => Carbon::today()->addDays(5)->toDateString(),
        ]);

        Artisan::call('notifications:send-billing-reminders');

        Notification::assertSentTo($student, AnnualFeeDue::class);
    }

    public function test_reminder_is_not_resent_when_already_delivered_for_the_cycle(): void
    {
        Notification::fake();

        $student = User::factory()->create(['is_active' => true]);
        $enrollment = Enrollment::factory()->for($student)->create(['is_exempt_from_tuition_fee' => false]);
        $dueDate = Carbon::today()->addDays(5)->toDateString();
        TuitionFee::factory()->for($enrollment)->create([
            'amount'   => 20000,
            'due_date' => $dueDate,
            'paid_at'  => null,
        ]);

        // A statement was already delivered for this cycle (recorded on the database channel).
        $student->notifications()->create([
            'id'      => (string) Str::uuid(),
            'type'    => BillingStatementNotification::class,
            'data'    => ['context' => 'upcoming', 'due_date' => $dueDate],
            'read_at' => null,
        ]);

        app(StatementService::class)->sendForCycle('upcoming', Carbon::parse($dueDate));

        Notification::assertNotSentTo($student, BillingStatementNotification::class);
    }

    public function test_authenticated_user_can_list_and_read_notifications(): void
    {
        $student = User::factory()->create();
        $charge = PieceCharge::factory()->create(['user_id' => $student->id]);

        $student->notify(new PieceChargeCreated($charge));

        Sanctum::actingAs($student);

        $response = $this->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', 'piece_charge_created');

        $id = $response->json('data.0.id');
        $this->assertIsString($id);

        $this->getJson('/api/v1/notifications?unread=true')->assertOk()->assertJsonCount(1, 'data');

        $this->postJson("/api/v1/notifications/{$id}/read")->assertOk();

        /** @var DatabaseNotification $notification */
        $notification = $student->notifications()->findOrFail($id);
        $this->assertNotNull($notification->read_at);

        $this->getJson('/api/v1/notifications?unread=true')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_authenticated_user_can_mark_all_notifications_as_read(): void
    {
        $student = User::factory()->create();
        $charge = PieceCharge::factory()->create(['user_id' => $student->id]);

        $student->notify(new PieceChargeCreated($charge));
        $student->notify(new PieceChargeCreated($charge));

        Sanctum::actingAs($student);

        $this->assertSame(2, $student->unreadNotifications()->count());

        $this->postJson('/api/v1/notifications/read-all')->assertNoContent();

        $this->assertSame(0, $student->fresh()?->unreadNotifications()->count());
    }
}
