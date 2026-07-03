<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PasswordUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_change_password_and_other_tokens_are_revoked(): void
    {
        $user = User::factory()->create();
        $current = $user->createToken('current')->plainTextToken;
        $user->createToken('other');

        $this->withToken($current)->putJson('/api/v1/user/password', [
            'current_password'      => 'password',
            'password'              => 'N0va!senha',
            'password_confirmation' => 'N0va!senha',
        ])->assertNoContent();

        $user->refresh();
        $this->assertTrue(Hash::check('N0va!senha', (string) $user->password));
        $this->assertSame(1, $user->tokens()->count());
    }

    public function test_password_change_requires_the_correct_current_password(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('api')->plainTextToken;

        $this->withToken($token)->putJson('/api/v1/user/password', [
            'current_password'      => 'wrong-password',
            'password'              => 'N0va!senha',
            'password_confirmation' => 'N0va!senha',
        ])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['current_password']]);
    }

    public function test_password_change_enforces_the_strong_password_policy(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->putJson('/api/v1/user/password', [
            'current_password'      => 'password',
            'password'              => 'weakpass',
            'password_confirmation' => 'weakpass',
        ])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['password']]);
    }

    public function test_new_password_must_differ_from_the_current_one(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->putJson('/api/v1/user/password', [
            'current_password'      => 'password',
            'password'              => 'password',
            'password_confirmation' => 'password',
        ])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['password']]);
    }

    public function test_changing_own_email_requires_the_current_password(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->patchJson("/api/v1/users/{$user->ulid}", ['email' => 'new@example.com'])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['current_password']]);
    }

    public function test_changing_own_email_succeeds_with_the_current_password(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->patchJson("/api/v1/users/{$user->ulid}", [
            'email'            => 'new@example.com',
            'current_password' => 'password',
        ])->assertOk();

        $this->assertDatabaseHas('users', ['id' => $user->id, 'email' => 'new@example.com']);
    }

    public function test_changing_only_the_name_does_not_require_the_password(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->patchJson("/api/v1/users/{$user->ulid}", ['name' => 'Renamed'])
            ->assertOk();
    }

    public function test_master_can_change_another_users_email_without_their_password(): void
    {
        $master = User::factory()->master()->create();
        $target = User::factory()->create();
        Sanctum::actingAs($master);

        $this->patchJson("/api/v1/users/{$target->ulid}", ['email' => 'moved@example.com'])
            ->assertOk();

        $this->assertDatabaseHas('users', ['id' => $target->id, 'email' => 'moved@example.com']);
    }
}
