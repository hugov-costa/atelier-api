<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Atelier;

use App\Models\User;
use App\Notifications\SetPasswordNotification;
use Illuminate\Auth\Passwords\PasswordBroker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class SetPasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_link_is_only_sent_to_passwordless_accounts(): void
    {
        Notification::fake();

        $newcomer = User::factory()->create(['email' => 'new@example.com', 'password' => null]);
        $established = User::factory()->create(['email' => 'old@example.com']);

        $this->postJson('/api/v1/set-password/request', ['email' => 'new@example.com'])->assertNoContent();
        $this->postJson('/api/v1/set-password/request', ['email' => 'old@example.com'])->assertNoContent();
        $this->postJson('/api/v1/set-password/request', ['email' => 'ghost@example.com'])->assertNoContent();

        Notification::assertSentTo($newcomer, SetPasswordNotification::class);
        Notification::assertNotSentTo($established, SetPasswordNotification::class);
    }

    public function test_a_valid_token_sets_the_password_and_allows_login(): void
    {
        $user = User::factory()->create(['email' => 'new@example.com', 'password' => null]);
        $token = $this->issueToken($user);

        $this->postJson('/api/v1/set-password/validate-token', [
            'email' => 'new@example.com',
            'token' => $token,
        ])->assertNoContent();

        $this->postJson('/api/v1/set-password/confirm', [
            'email'                 => 'new@example.com',
            'token'                 => $token,
            'password'              => 'Sup3r!secret',
            'password_confirmation' => 'Sup3r!secret',
        ])->assertNoContent();

        $this->assertNotNull($user->refresh()->password);
        $this->assertTrue($user->hasVerifiedEmail());

        $this->postJson('/api/v1/login', [
            'email'    => 'new@example.com',
            'password' => 'Sup3r!secret',
        ])->assertOk();
    }

    public function test_an_invalid_token_is_rejected(): void
    {
        User::factory()->create(['email' => 'new@example.com', 'password' => null]);

        $this->postJson('/api/v1/set-password/confirm', [
            'email'                 => 'new@example.com',
            'token'                 => 'totally-wrong-token',
            'password'              => 'Sup3r!secret',
            'password_confirmation' => 'Sup3r!secret',
        ])->assertStatus(422);
    }

    private function issueToken(User $user): string
    {
        /** @var PasswordBroker $broker */
        $broker = Password::broker('set_password');

        return $broker->getRepository()->create($user);
    }
}
