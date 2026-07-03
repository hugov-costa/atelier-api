<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CsrfTest extends TestCase
{
    use RefreshDatabase;

    public function test_cookie_authenticated_mutations_require_a_csrf_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('api')->plainTextToken;

        $this->withCredentials()
            ->withCookie('access_token', $token)
            ->postJson('/api/v1/logout')
            ->assertStatus(419)
            ->assertJson(['status' => 419, 'title' => 'CSRF Token Mismatch']);
    }

    public function test_matching_csrf_token_passes(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('api')->plainTextToken;

        $this->withCredentials()
            ->withCookie('access_token', $token)
            ->withUnencryptedCookie('XSRF-TOKEN', 'csrf-value')
            ->withHeader('X-XSRF-TOKEN', 'csrf-value')
            ->postJson('/api/v1/logout')
            ->assertNoContent();
    }

    public function test_bearer_requests_skip_csrf(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('api')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/v1/logout')
            ->assertNoContent();
    }
}
