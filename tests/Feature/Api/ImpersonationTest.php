<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Bill;
use App\Models\User;
use App\Services\ImpersonationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImpersonationTest extends TestCase
{
    use RefreshDatabase;

    private function bearer(string $token): self
    {
        return $this->withHeader('Authorization', 'Bearer '.$token);
    }

    public function test_a_master_can_start_impersonating_a_user(): void
    {
        $master = User::factory()->master()->create();
        $target = User::factory()->create();
        $token = $master->createToken('api')->plainTextToken;

        $this->bearer($token)
            ->postJson("/api/v1/users/{$target->ulid}/impersonate", ['reason' => 'Investigating ticket #42'])
            ->assertOk()
            ->assertJsonPath('data.user.id', $target->ulid)
            ->assertJsonStructure(['data' => ['user', 'expires_at']]);

        $this->assertDatabaseHas('impersonations', [
            'impersonator_id' => $master->id,
            'impersonated_id' => $target->id,
            'reason'          => 'Investigating ticket #42',
            'ended_at'        => null,
        ]);
    }

    public function test_a_non_master_cannot_start_impersonation(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create();
        $token = $admin->createToken('api')->plainTextToken;

        $this->bearer($token)
            ->postJson("/api/v1/users/{$target->ulid}/impersonate", ['reason' => 'because'])
            ->assertForbidden();
    }

    public function test_a_master_cannot_impersonate_another_master(): void
    {
        $master = User::factory()->master()->create();
        $other = User::factory()->master()->create();
        $token = $master->createToken('api')->plainTextToken;

        $this->bearer($token)
            ->postJson("/api/v1/users/{$other->ulid}/impersonate", ['reason' => 'because'])
            ->assertForbidden();
    }

    public function test_a_reason_is_required_to_impersonate(): void
    {
        $master = User::factory()->master()->create();
        $target = User::factory()->create();
        $token = $master->createToken('api')->plainTextToken;

        $this->bearer($token)
            ->postJson("/api/v1/users/{$target->ulid}/impersonate", [])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['reason']]);
    }

    public function test_impersonated_session_reports_the_impersonator(): void
    {
        $master = User::factory()->master()->create();
        $target = User::factory()->create();

        $result = app(ImpersonationService::class)->start($master, $target, 'support', '127.0.0.1');

        $this->bearer($result['token'])
            ->getJson('/api/v1/user')
            ->assertOk()
            ->assertJsonPath('data.id', $target->ulid)
            ->assertJsonPath('data.impersonated_by', $master->ulid);
    }

    public function test_identity_and_credential_actions_are_blocked_while_impersonating(): void
    {
        $master = User::factory()->master()->create();
        $target = User::factory()->create();

        $result = app(ImpersonationService::class)->start($master, $target, 'support', '127.0.0.1');
        $token = $result['token'];

        $this->bearer($token)->putJson('/api/v1/user/password', [
            'current_password'      => 'password',
            'password'              => 'An0ther-secret!',
            'password_confirmation' => 'An0ther-secret!',
        ])->assertForbidden();

        $this->bearer($token)->deleteJson('/api/v1/two-factor', ['password' => 'password'])->assertForbidden();
        $this->bearer($token)->getJson('/api/v1/user/export')->assertForbidden();
        $this->bearer($token)->deleteJson('/api/v1/user', ['password' => 'password'])->assertForbidden();
    }

    public function test_low_risk_writes_during_impersonation_are_attributed_to_the_master(): void
    {
        $master = User::factory()->master()->create();
        $target = User::factory()->create();

        $result = app(ImpersonationService::class)->start($master, $target, 'support', '127.0.0.1');

        $this->bearer($result['token'])
            ->patchJson("/api/v1/users/{$target->ulid}", ['name' => 'Renamed While Impersonated'])
            ->assertOk();

        $this->assertDatabaseHas('audits', [
            'auditable_id'    => $target->id,
            'event'           => 'updated',
            'impersonator_id' => $master->ulid,
        ]);
    }

    public function test_stopping_impersonation_ends_the_session(): void
    {
        $master = User::factory()->master()->create();
        $target = User::factory()->create();

        $result = app(ImpersonationService::class)->start($master, $target, 'support', '127.0.0.1');

        $this->bearer($result['token'])->deleteJson('/api/v1/impersonate')->assertNoContent();

        $this->assertDatabaseHas('impersonations', [
            'impersonated_id' => $target->id,
        ]);
        $this->assertDatabaseMissing('impersonations', [
            'impersonated_id' => $target->id,
            'ended_at'        => null,
        ]);
    }

    public function test_export_includes_the_impersonation_access_log(): void
    {
        $master = User::factory()->master()->create(['name' => 'Master Jane']);
        $target = User::factory()->create();

        app(ImpersonationService::class)->start($master, $target, 'support visit', '127.0.0.1');

        $token = $target->createToken('api')->plainTextToken;

        $this->bearer($token)
            ->getJson('/api/v1/user/export')
            ->assertOk()
            ->assertJsonPath('data.access_log.0.impersonator', 'Master Jane')
            ->assertJsonPath('data.access_log.0.reason', 'support visit');
    }

    public function test_atelier_writes_made_while_impersonating_are_tagged_with_the_impersonator(): void
    {
        $master = User::factory()->master()->create();
        // Impersonate a staff member so the atelier policy allows the write.
        $staff = User::factory()->admin()->create();

        $result = app(ImpersonationService::class)->start($master, $staff, 'support', '127.0.0.1');

        $this->bearer($result['token'])
            ->postJson('/api/v1/bills', [
                'name'            => 'Energia',
                'due_date'        => '2026-03-15',
                'is_recurrent'    => false,
                'reference_month' => 3,
                'reference_year'  => 2026,
                'value'           => 10000,
            ])
            ->assertCreated();

        // The audit for the financial write records the real actor behind the session.
        $this->assertDatabaseHas('audits', [
            'auditable_type'  => Bill::class,
            'event'           => 'created',
            'impersonator_id' => $master->ulid,
        ]);
    }
}
