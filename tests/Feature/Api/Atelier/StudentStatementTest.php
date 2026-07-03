<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Atelier;

use App\Models\Enrollment;
use App\Models\PieceCharge;
use App\Models\TuitionFee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StudentStatementTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_view_a_students_statement(): void
    {
        Carbon::setTestNow('2026-07-01 12:00:00');

        Sanctum::actingAs(User::factory()->admin()->create());

        $student = User::factory()->create();
        $enrollment = Enrollment::factory()->create([
            'user_id'             => $student->id,
            'annual_fee'          => 30000,
            'annual_fee_is_paid'  => false,
            'annual_fee_paid_at'  => null,
            'annual_fee_due_date' => now()->addMonth()->toDateString(),
        ]);

        TuitionFee::factory()->create([
            'enrollment_id' => $enrollment->id,
            'amount'        => 20000,
            'due_date'      => now()->subMonths(2)->toDateString(),
            'paid_at'       => now()->subMonths(2),
        ]);

        TuitionFee::factory()->create([
            'enrollment_id' => $enrollment->id,
            'amount'        => 15000,
            'due_date'      => now()->subDays(5)->toDateString(),
            'paid_at'       => null,
        ]);

        PieceCharge::factory()->create([
            'user_id'  => $student->id,
            'amount'   => 5000,
            'due_date' => now()->addWeeks(2)->toDateString(),
            'paid_at'  => null,
        ]);

        $this->getJson("/api/v1/students/{$student->ulid}/statement")
            ->assertOk()
            ->assertJsonPath('data.student.id', $student->ulid)
            ->assertJsonPath('data.balance.overdue', 15000)
            ->assertJsonPath('data.balance.upcoming', 35000)
            ->assertJsonPath('data.balance.total_outstanding', 50000)
            ->assertJsonPath('data.items.tuition.0.status', 'paid')
            ->assertJsonPath('data.items.tuition.1.status', 'overdue')
            ->assertJsonPath('data.items.annual_fees.0.status', 'open')
            ->assertJsonPath('data.items.piece_charges.0.status', 'open')
            ->assertJsonCount(1, 'data.history')
            ->assertJsonPath('data.history.0.type', 'tuition')
            ->assertJsonPath('data.history.0.amount', 20000);

        Carbon::setTestNow();
    }

    public function test_student_can_view_their_own_statement(): void
    {
        $student = User::factory()->create();
        Sanctum::actingAs($student);

        $this->getJson("/api/v1/students/{$student->ulid}/statement")
            ->assertOk()
            ->assertJsonPath('data.student.id', $student->ulid);
    }

    public function test_student_cannot_view_another_students_statement(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $other = User::factory()->create();

        $this->getJson("/api/v1/students/{$other->ulid}/statement")
            ->assertForbidden();
    }
}
