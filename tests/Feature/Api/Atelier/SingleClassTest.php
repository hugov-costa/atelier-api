<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Atelier;

use App\Models\SingleClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SingleClassTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_create_a_single_class_with_attendees(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $students = User::factory()->count(2)->create();

        $this->postJson('/api/v1/single-classes', [
            'start_datetime' => '2026-03-10 10:00:00',
            'end_datetime'   => '2026-03-10 11:00:00',
            'is_replacement' => false,
            'price'          => 5000,
            'user_ids'       => $students->pluck('ulid')->all(),
        ])->assertCreated()->assertJsonCount(2, 'data.users');

        $this->assertDatabaseCount('single_class_user', 2);
    }

    public function test_end_must_be_after_start(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $student = User::factory()->create();

        $this->postJson('/api/v1/single-classes', [
            'start_datetime' => '2026-03-10 11:00:00',
            'end_datetime'   => '2026-03-10 10:00:00',
            'is_replacement' => false,
            'user_ids'       => [$student->ulid],
        ])->assertStatus(422)->assertJsonValidationErrors('end_datetime');
    }

    public function test_updating_attendees_replaces_the_set(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $class = SingleClass::factory()->create();
        $class->users()->attach(User::factory()->count(2)->create()->pluck('id')->all());

        $replacement = User::factory()->create();

        $this->putJson("/api/v1/single-classes/{$class->ulid}", ['user_ids' => [$replacement->ulid]])
            ->assertOk()->assertJsonCount(1, 'data.users');

        $this->assertDatabaseCount('single_class_user', 1);
    }

    public function test_students_cannot_manage_single_classes(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/single-classes')->assertForbidden();
    }
}
