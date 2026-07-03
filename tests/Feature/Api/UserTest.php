<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_access_the_authenticated_user_endpoint(): void
    {
        $this->getJson('/api/v1/user')->assertUnauthorized();
    }

    public function test_authenticated_user_is_returned(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/user')
            ->assertOk()
            ->assertJson([
                'data' => [
                    'id'    => $user->ulid,
                    'name'  => $user->name,
                    'email' => $user->email,
                ],
            ])
            ->assertJsonMissingPath('data.password');
    }
}
