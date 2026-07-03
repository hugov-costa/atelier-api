<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_can_login(): void
    {
        User::factory()->create([
            'email'    => 'john@example.com',
            'password' => 'super-secret-password',
        ]);

        $this->postJson('/api/v1/login', [
            'email'    => 'john@example.com',
            'password' => 'super-secret-password',
        ])
            ->assertOk()
            ->assertJsonStructure(['data' => ['user' => ['id', 'email'], 'token', 'token_type'], 'message']);
    }

    public function test_login_fails_with_wrong_credentials(): void
    {
        User::factory()->create([
            'email'    => 'john@example.com',
            'password' => 'super-secret-password',
        ]);

        $this->postJson('/api/v1/login', [
            'email'    => 'john@example.com',
            'password' => 'wrong-password',
        ])->assertStatus(422);
    }

    public function test_users_can_logout_and_the_token_is_revoked(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('api')->plainTextToken;

        $this->withToken($token)->postJson('/api/v1/logout')->assertNoContent();

        $this->assertSame(0, $user->tokens()->count());
    }
}
