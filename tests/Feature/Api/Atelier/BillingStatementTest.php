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
use App\Services\StatementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BillingStatementTest extends TestCase
{
    use RefreshDatabase;

    public function test_generating_tuition_sends_billing_statement_to_student(): void
    {
        Notification::fake();
        Sanctum::actingAs(User::factory()->admin()->create());

        $student = User::factory()->create();
        $enrollment = Enrollment::factory()->for($student)->create(['is_exempt_from_tuition_fee' => false]);

        Setting::query()->update(['tuition_monthly_cost' => 20000]);

        $this->postJson('/api/v1/tuition-fees', ['enrollment_id' => $enrollment->ulid])->assertCreated();

        Notification::assertSentTo(
            $student,
            BillingStatementNotification::class,
            function (BillingStatementNotification $notification) use ($student): bool {
                return $notification->via($student) === ['mail', 'database'];
            }
        );
    }

    public function test_channel_matrix_is_mail_and_database(): void
    {
        $statement = $this->sampleStatement();

        $this->assertSame(
            ['mail', 'database'],
            (new BillingStatementNotification($statement, 'generated'))->via(User::factory()->make()),
        );
    }

    public function test_build_for_user_cycle_itemizes_tuition_and_pieces(): void
    {
        $student = User::factory()->create();
        Enrollment::factory()->for($student)->create();

        $dueDate = '2026-03-10';

        $enrollmentFee = Enrollment::query()->where('user_id', $student->id)->firstOrFail();
        TuitionFee::factory()->for($enrollmentFee, 'enrollment')->create([
            'amount'   => 20000,
            'due_date' => $dueDate,
            'paid_at'  => null,
        ]);

        $clay = Clay::factory()->create(['name' => 'Grés', 'price' => 1000]);
        $piece = Piece::factory()->student()->create([
            'user_id'          => $student->id,
            'clay_id'          => $clay->id,
            'clay_amount'      => 2,
            'clay_unit_price'  => 1000,
            'glaze_id'         => null,
            'glaze_amount'     => null,
            'glaze_unit_price' => null,
            'name'             => 'Tigela',
        ]);
        PieceCharge::factory()->create([
            'piece_id' => $piece->id,
            'user_id'  => $student->id,
            'amount'   => 3000,
            'due_date' => $dueDate,
            'paid_at'  => null,
        ]);

        $statement = app(StatementService::class)->buildForUserCycle($dueDate, $student);

        $this->assertNotNull($statement);
        $this->assertSame(20000, $statement['tuition_amount']);
        $this->assertCount(1, $statement['pieces']);

        $pieceLine = $statement['pieces'][0];
        $this->assertSame(3000, $pieceLine['total']);
        $this->assertSame(2000, $pieceLine['materials'][0]['cost']);
        $this->assertSame('Grés', $pieceLine['materials'][0]['label']);

        $this->assertSame(23000, $statement['total']);
    }

    public function test_exempt_from_piece_charges_raises_no_charge(): void
    {
        Notification::fake();
        Sanctum::actingAs(User::factory()->admin()->create());

        $clay = Clay::factory()->create(['price' => 1000]);
        $student = User::factory()->create();
        Enrollment::factory()->for($student)->create(['is_exempt_from_piece_charges' => true]);

        $this->postJson('/api/v1/pieces', [
            'kind'        => 'student',
            'clay_id'     => $clay->ulid,
            'clay_amount' => 1,
            'user_id'     => $student->ulid,
            'name'        => 'Caneca',
        ])->assertCreated();

        $this->assertSame(0, PieceCharge::query()->count());
    }

    public function test_tuition_exempt_student_with_piece_gets_pieces_only_statement(): void
    {
        Notification::fake();

        $student = User::factory()->create();
        Enrollment::factory()->for($student)->create(['is_exempt_from_tuition_fee' => true]);

        $dueDate = '2026-04-10';

        $clay = Clay::factory()->create(['name' => 'Porcelana', 'price' => 1500]);
        $piece = Piece::factory()->student()->create([
            'user_id'         => $student->id,
            'clay_id'         => $clay->id,
            'clay_amount'     => 1,
            'clay_unit_price' => 1500,
            'name'            => 'Prato',
        ]);
        PieceCharge::factory()->create([
            'piece_id' => $piece->id,
            'user_id'  => $student->id,
            'amount'   => 1500,
            'due_date' => $dueDate,
            'paid_at'  => null,
        ]);

        $statement = app(StatementService::class)->buildForUserCycle($dueDate, $student);

        $this->assertNotNull($statement);
        $this->assertNull($statement['tuition_amount']);
        $this->assertNotEmpty($statement['pieces']);

        app(StatementService::class)->sendForCycle('generated', Carbon::parse($dueDate));

        Notification::assertSentTo($student, BillingStatementNotification::class);
    }

    public function test_fully_exempt_student_with_nothing_due_gets_no_statement(): void
    {
        Notification::fake();

        $student = User::factory()->create();
        Enrollment::factory()->for($student)->create([
            'is_exempt_from_tuition_fee'   => true,
            'is_exempt_from_piece_charges' => true,
        ]);

        $dueDate = '2026-05-10';

        $this->assertNull(app(StatementService::class)->buildForUserCycle($dueDate, $student));

        app(StatementService::class)->sendForCycle('generated', Carbon::parse($dueDate));

        Notification::assertNothingSent();
    }

    public function test_staff_can_upload_and_remove_the_atelier_logo(): void
    {
        Storage::fake('minio_public');
        Sanctum::actingAs(User::factory()->admin()->create());

        $response = $this->postJson('/api/v1/settings/logo', [
            'logo' => UploadedFile::fake()->image('logo.png'),
        ])->assertOk();

        $setting = Setting::current();
        $this->assertNotNull($setting->logo_path);
        Storage::disk('minio_public')->assertExists($setting->logo_path);
        $this->assertNotNull($response->json('data.logo_url'));

        $this->deleteJson('/api/v1/settings/logo')->assertNoContent();

        $this->assertNull(Setting::current()->logo_path);
    }

    /**
     * @return array{
     *     student_name: string,
     *     enrollment_number: string|null,
     *     reference_month: string,
     *     due_date: string,
     *     tuition_amount: int|null,
     *     pieces: list<array{
     *         name: string,
     *         materials: list<array{label: string, quantity: float, unit: string, unit_price: int, cost: int}>,
     *         firing: list<array{label: string, price: int}>,
     *         total: int
     *     }>,
     *     total: int,
     *     logo_data_uri: string|null
     * }
     */
    private function sampleStatement(): array
    {
        return [
            'student_name'      => 'Aluno',
            'enrollment_number' => null,
            'reference_month'   => 'Março de 2026',
            'due_date'          => '2026-03-01',
            'tuition_amount'    => 20000,
            'pieces'            => [],
            'total'             => 20000,
            'logo_data_uri'     => null,
        ];
    }
}
