<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_admins_can_list_users(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $this->getJson('/api/v1/users')->assertForbidden();

        Sanctum::actingAs(User::factory()->admin()->create());
        $this->getJson('/api/v1/users')->assertOk()->assertJsonStructure(['data', 'meta']);
    }

    public function test_a_user_can_update_themselves(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->patchJson("/api/v1/users/{$user->ulid}", ['name' => 'New Name'])
            ->assertOk()
            ->assertJsonPath('data.name', 'New Name');

        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'New Name']);
    }

    public function test_a_user_cannot_update_another_user(): void
    {
        $actor = User::factory()->create();
        $other = User::factory()->create();
        Sanctum::actingAs($actor);

        $this->patchJson("/api/v1/users/{$other->ulid}", ['name' => 'Hacked'])
            ->assertForbidden();
    }

    public function test_a_master_can_update_any_user(): void
    {
        $master = User::factory()->master()->create();
        $other = User::factory()->create();
        Sanctum::actingAs($master);

        $this->patchJson("/api/v1/users/{$other->ulid}", ['name' => 'Renamed By Master'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Renamed By Master');
    }

    public function test_an_admin_can_read_but_not_manage_other_users(): void
    {
        $admin = User::factory()->admin()->create();
        $other = User::factory()->create();
        Sanctum::actingAs($admin);

        $this->getJson("/api/v1/users/{$other->ulid}")->assertOk();

        $this->patchJson("/api/v1/users/{$other->ulid}", ['name' => 'Renamed By Admin'])
            ->assertForbidden();
        $this->deleteJson("/api/v1/users/{$other->ulid}")->assertForbidden();
    }

    public function test_masters_can_delete_other_users_but_not_themselves(): void
    {
        $master = User::factory()->master()->create();
        $other = User::factory()->create();
        Sanctum::actingAs($master);

        $this->deleteJson("/api/v1/users/{$other->ulid}")->assertNoContent();
        $this->assertSoftDeleted('users', ['id' => $other->id]);

        $this->deleteJson("/api/v1/users/{$master->ulid}")->assertForbidden();
    }

    public function test_masters_can_restore_a_soft_deleted_user(): void
    {
        $master = User::factory()->master()->create();
        $other = User::factory()->create();
        Sanctum::actingAs($master);

        $this->deleteJson("/api/v1/users/{$other->ulid}")->assertNoContent();
        $this->assertSoftDeleted('users', ['id' => $other->id]);

        $this->postJson("/api/v1/users/{$other->ulid}/restore")
            ->assertOk()
            ->assertJsonPath('data.id', $other->ulid);

        $this->assertDatabaseHas('users', ['id' => $other->id, 'deleted_at' => null]);
    }

    public function test_a_regular_user_cannot_restore_a_user(): void
    {
        $admin = User::factory()->admin()->create();
        $other = User::factory()->create();
        $other->delete();

        Sanctum::actingAs(User::factory()->create());

        $this->postJson("/api/v1/users/{$other->ulid}/restore")->assertForbidden();
    }

    public function test_a_user_can_view_themselves_but_not_others(): void
    {
        $actor = User::factory()->create();
        $other = User::factory()->create();
        Sanctum::actingAs($actor);

        $this->getJson("/api/v1/users/{$actor->ulid}")->assertOk();
        $this->getJson("/api/v1/users/{$other->ulid}")->assertForbidden();
    }

    public function test_admins_can_filter_users_by_name_or_email(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Admin', 'email' => 'admin@example.com']);
        User::factory()->create(['name' => 'Alice Findme', 'email' => 'alice@example.com']);
        User::factory()->create(['name' => 'Bob', 'email' => 'findme.bob@example.com']);
        User::factory()->create(['name' => 'Carol', 'email' => 'carol@example.com']);
        Sanctum::actingAs($admin);

        $data = $this->getJson('/api/v1/users?search=findme')->assertOk()->json('data');
        $emails = array_column(is_array($data) ? $data : [], 'email');

        $this->assertContains('alice@example.com', $emails);
        $this->assertContains('findme.bob@example.com', $emails);
        $this->assertNotContains('carol@example.com', $emails);
        $this->assertNotContains('admin@example.com', $emails);
    }

    public function test_admins_can_sort_users_by_a_whitelisted_column(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Zoe']);
        User::factory()->create(['name' => 'Aaron']);
        User::factory()->create(['name' => 'Mary']);
        Sanctum::actingAs($admin);

        $data = $this->getJson('/api/v1/users?sort=name&direction=asc')->assertOk()->json('data');
        $names = array_column(is_array($data) ? $data : [], 'name');

        $sorted = $names;
        sort($sorted);

        $this->assertSame($sorted, $names);
    }

    public function test_an_unknown_sort_column_falls_back_to_the_default_order(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->count(3)->create();
        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/users?sort=password&direction=asc')->assertOk();
    }

    public function test_admins_can_paginate_users_with_a_cursor(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->count(5)->create();
        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/users?paginator=cursor&per_page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure([
                'data',
                'meta' => ['per_page', 'next_cursor', 'prev_cursor'],
            ]);
    }

    public function test_a_master_can_erase_a_user_on_request(): void
    {
        $master = User::factory()->master()->create();
        $target = User::factory()->create(['name' => 'Jane', 'email' => 'jane@example.com']);
        Sanctum::actingAs($master);

        $this->postJson("/api/v1/users/{$target->ulid}/erase")->assertNoContent();

        $erased = User::withTrashed()->findOrFail($target->id);
        $this->assertNotSame('jane@example.com', $erased->email);
        $this->assertStringEndsWith('@deleted.invalid', $erased->email);
        $this->assertNotNull($erased->deleted_at);
    }

    public function test_non_masters_cannot_erase_a_user(): void
    {
        $target = User::factory()->create();

        Sanctum::actingAs(User::factory()->admin()->create());
        $this->postJson("/api/v1/users/{$target->ulid}/erase")->assertForbidden();

        Sanctum::actingAs(User::factory()->create());
        $this->postJson("/api/v1/users/{$target->ulid}/erase")->assertForbidden();
    }

    public function test_a_master_cannot_erase_themselves(): void
    {
        $master = User::factory()->master()->create();
        Sanctum::actingAs($master);

        $this->postJson("/api/v1/users/{$master->ulid}/erase")->assertForbidden();
    }
}
