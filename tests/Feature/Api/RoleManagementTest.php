<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RoleManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_master_can_assign_a_role_to_another_user(): void
    {
        $master = User::factory()->master()->create();
        $target = User::factory()->create();
        Sanctum::actingAs($master);

        $this->patchJson("/api/v1/users/{$target->ulid}", ['role' => 'admin'])
            ->assertOk()
            ->assertJsonPath('data.role', 'admin');

        $this->assertDatabaseHas('users', ['id' => $target->id, 'role' => 'admin']);
    }

    public function test_a_non_master_cannot_change_a_role(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->patchJson("/api/v1/users/{$user->ulid}", ['role' => 'master'])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['role']]);

        $this->assertDatabaseHas('users', ['id' => $user->id, 'role' => 'user']);
    }

    public function test_the_last_master_cannot_be_demoted(): void
    {
        $master = User::factory()->master()->create();
        Sanctum::actingAs($master);

        $this->patchJson("/api/v1/users/{$master->ulid}", ['role' => 'admin'])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['role']]);

        $this->assertDatabaseHas('users', ['id' => $master->id, 'role' => 'master']);
    }

    public function test_a_master_can_be_demoted_when_another_master_exists(): void
    {
        $master = User::factory()->master()->create();
        $other = User::factory()->master()->create();
        Sanctum::actingAs($master);

        $this->patchJson("/api/v1/users/{$other->ulid}", ['role' => 'admin'])
            ->assertOk()
            ->assertJsonPath('data.role', 'admin');

        $this->assertDatabaseHas('users', ['id' => $other->id, 'role' => 'admin']);
    }
}
