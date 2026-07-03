<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\Cookie;
use Tests\TestCase;

class CookieAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_sets_an_http_only_token_cookie_and_a_readable_csrf_cookie(): void
    {
        User::factory()->create([
            'email'    => 'john@example.com',
            'password' => 'super-secret-password',
        ]);

        $response = $this->postJson('/api/v1/login', [
            'email'    => 'john@example.com',
            'password' => 'super-secret-password',
        ])->assertOk()->assertCookie('access_token')->assertCookie('XSRF-TOKEN');

        /** @var Collection<int, Cookie> $cookies */
        $cookies = collect($response->headers->getCookies());

        $tokenCookie = $cookies->first(fn (Cookie $c): bool => $c->getName() === 'access_token');
        $csrfCookie = $cookies->first(fn (Cookie $c): bool => $c->getName() === 'XSRF-TOKEN');

        $this->assertInstanceOf(Cookie::class, $tokenCookie);
        $this->assertInstanceOf(Cookie::class, $csrfCookie);
        $this->assertTrue($tokenCookie->isHttpOnly());
        $this->assertFalse($csrfCookie->isHttpOnly());
    }

    public function test_requests_authenticate_from_the_token_cookie(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('api')->plainTextToken;

        $this->withCredentials()
            ->withCookie('access_token', $token)
            ->getJson('/api/v1/user')
            ->assertOk()
            ->assertJsonPath('data.id', $user->ulid);
    }

    public function test_an_explicit_bearer_header_still_works(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('api')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/user')
            ->assertOk()
            ->assertJsonPath('data.id', $user->ulid);
    }

    public function test_logout_clears_the_token_cookie(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('api')->plainTextToken;

        $this->withCredentials()
            ->withCookie('access_token', $token)
            ->withUnencryptedCookie('XSRF-TOKEN', 'csrf-value')
            ->withHeader('X-XSRF-TOKEN', 'csrf-value')
            ->postJson('/api/v1/logout')
            ->assertNoContent()
            ->assertCookieExpired('access_token');
    }
}
