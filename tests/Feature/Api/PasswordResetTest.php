<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\User;
use App\Notifications\ResetPasswordQueued;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_reset_link_is_queued_for_known_emails(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'john@example.com']);

        $this->postJson('/api/v1/forgot-password', ['email' => 'john@example.com'])
            ->assertNoContent();

        Notification::assertSentTo($user, ResetPasswordQueued::class);
    }

    public function test_users_can_reset_their_password(): void
    {
        $user = User::factory()->create(['email' => 'john@example.com']);
        $token = Password::createToken($user);

        $this->postJson('/api/v1/reset-password', [
            'token'                 => $token,
            'email'                 => 'john@example.com',
            'password'              => 'Sup3r-secret!',
            'password_confirmation' => 'Sup3r-secret!',
        ])->assertNoContent();

        $user->refresh();

        $this->assertTrue(Hash::check('Sup3r-secret!', (string) $user->password));
    }

    public function test_reset_rejects_weak_passwords(): void
    {
        $user = User::factory()->create(['email' => 'john@example.com']);
        $token = Password::createToken($user);

        $this->postJson('/api/v1/reset-password', [
            'token'                 => $token,
            'email'                 => 'john@example.com',
            'password'              => 'password',
            'password_confirmation' => 'password',
        ])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['password']]);
    }

    public function test_reset_email_links_to_the_frontend(): void
    {
        $user = User::factory()->create(['email' => 'john@example.com']);

        $actionUrl = (string) (new ResetPasswordQueued('sample-token'))->toMail($user)->actionUrl;

        $this->assertStringContainsString('http://localhost:3000/reset-password', $actionUrl);
        $this->assertStringContainsString('token=sample-token', $actionUrl);
    }

    public function test_reset_fails_with_an_invalid_token(): void
    {
        User::factory()->create(['email' => 'john@example.com']);

        $this->postJson('/api/v1/reset-password', [
            'token'                 => 'totally-invalid-token',
            'email'                 => 'john@example.com',
            'password'              => 'Sup3r-secret!',
            'password_confirmation' => 'Sup3r-secret!',
        ])->assertStatus(422);
    }
}
