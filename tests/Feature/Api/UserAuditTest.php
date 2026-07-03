<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_list_audits(): void
    {
        $this->getJson('/api/v1/users/audits')->assertUnauthorized();

        $user = User::factory()->create();
        $this->getJson("/api/v1/users/{$user->ulid}/audits")->assertUnauthorized();
    }

    public function test_non_admins_cannot_list_audits(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/users/audits')->assertForbidden();
        $this->getJson("/api/v1/users/{$user->ulid}/audits")->assertForbidden();
    }

    public function test_admins_can_list_user_audits(): void
    {
        $admin = User::factory()->admin()->create();

        $target = User::factory()->create();
        $target->update(['name' => 'Renamed']);

        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/users/audits')
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'event', 'auditable_id', 'old_values', 'new_values']]])
            ->assertJsonFragment(['event' => 'created'])
            ->assertJsonFragment(['event' => 'updated']);
    }

    public function test_audits_can_be_filtered_by_event(): void
    {
        $admin = User::factory()->admin()->create();

        $target = User::factory()->create();
        $target->update(['name' => 'Renamed']);

        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/users/audits?event=updated')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['event' => 'updated']);
    }

    public function test_per_user_route_returns_only_that_users_audits(): void
    {
        $admin = User::factory()->admin()->create();

        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $userA->update(['name' => 'A renamed']);
        $userB->update(['name' => 'B renamed']);

        Sanctum::actingAs($admin);

        $response = $this->getJson("/api/v1/users/{$userA->ulid}/audits")
            ->assertOk()
            ->assertJsonCount(2, 'data');

        /** @var array<int, array<string, mixed>> $audits */
        $audits = $response->json('data');

        foreach ($audits as $audit) {
            $this->assertSame($userA->id, $audit['auditable_id']);
        }
    }
}
