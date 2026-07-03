<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Atelier;

use App\Models\RecurrentClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RecurrentClassTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_create_a_recurrent_class(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $students = User::factory()->count(2)->create();

        $this->postJson('/api/v1/recurrent-classes', [
            'day_of_the_week' => 2,
            'start_time'      => '10:00:00',
            'end_time'        => '11:00:00',
            'user_ids'        => $students->pluck('ulid')->all(),
        ])->assertCreated()->assertJsonCount(2, 'data.users');
    }

    public function test_end_time_must_be_after_start_time(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $student = User::factory()->create();

        $this->postJson('/api/v1/recurrent-classes', [
            'day_of_the_week' => 2,
            'start_time'      => '11:00:00',
            'end_time'        => '10:00:00',
            'user_ids'        => [$student->ulid],
        ])->assertStatus(422)->assertJsonValidationErrors('end_time');
    }

    public function test_overlapping_classes_on_the_same_day_are_rejected(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $student = User::factory()->create();

        RecurrentClass::factory()->create([
            'day_of_the_week' => 3,
            'start_time'      => '10:00:00',
            'end_time'        => '12:00:00',
        ]);

        $this->postJson('/api/v1/recurrent-classes', [
            'day_of_the_week' => 3,
            'start_time'      => '11:00:00',
            'end_time'        => '13:00:00',
            'user_ids'        => [$student->ulid],
        ])->assertStatus(422)->assertJsonValidationErrors('end_time');
    }

    public function test_non_overlapping_class_on_the_same_day_is_accepted(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $student = User::factory()->create();

        RecurrentClass::factory()->create([
            'day_of_the_week' => 4,
            'start_time'      => '10:00:00',
            'end_time'        => '12:00:00',
        ]);

        $this->postJson('/api/v1/recurrent-classes', [
            'day_of_the_week' => 4,
            'start_time'      => '12:00:00',
            'end_time'        => '13:00:00',
            'user_ids'        => [$student->ulid],
        ])->assertCreated();
    }
}
