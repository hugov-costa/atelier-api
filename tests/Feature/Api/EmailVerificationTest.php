<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\User;
use App\Notifications\VerifyEmailQueued;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_can_verify_their_email(): void
    {
        $user = User::factory()->unverified()->create();

        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'id'   => $user->id,
            'hash' => sha1($user->getEmailForVerification()),
        ]);

        Sanctum::actingAs($user);

        $this->getJson($url)->assertNoContent();

        $user->refresh();

        $this->assertTrue($user->hasVerifiedEmail());
    }

    public function test_verification_can_be_resent(): void
    {
        Notification::fake();

        $user = User::factory()->unverified()->create();

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/email/verification-notification')->assertNoContent();

        Notification::assertSentTo($user, VerifyEmailQueued::class);
    }

    public function test_changing_the_email_marks_the_account_unverified_and_resends_verification(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->patchJson("/api/v1/users/{$user->ulid}", [
            'email'            => 'changed@example.com',
            'current_password' => 'password',
        ])->assertOk();

        $user->refresh();

        $this->assertFalse($user->hasVerifiedEmail());
        Notification::assertSentTo($user, VerifyEmailQueued::class);
    }

    public function test_verification_email_links_to_the_frontend(): void
    {
        $user = User::factory()->unverified()->create();

        $actionUrl = (string) (new VerifyEmailQueued)->toMail($user)->actionUrl;

        $this->assertStringContainsString('http://localhost:3000/verify-email', $actionUrl);
        $this->assertStringContainsString('verify_url=', $actionUrl);
    }
}
