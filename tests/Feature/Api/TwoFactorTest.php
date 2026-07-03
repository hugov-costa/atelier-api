<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class TwoFactorTest extends TestCase
{
    use RefreshDatabase;

    private function currentOtp(User $user): string
    {
        return (new Google2FA)->getCurrentOtp($user->two_factor_secret ?? '');
    }

    public function test_a_user_can_enable_and_confirm_two_factor(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/two-factor/enable', ['password' => 'password'])
            ->assertOk()
            ->assertJsonStructure(['data' => ['secret', 'qr_code_url', 'recovery_codes'], 'message']);

        $user->refresh();
        $this->assertFalse($user->hasEnabledTwoFactor());

        $this->getJson('/api/v1/user')
            ->assertOk()
            ->assertJsonPath('data.two_factor_enabled', false);

        $this->postJson('/api/v1/two-factor/confirm', ['code' => $this->currentOtp($user)])
            ->assertNoContent();

        $user->refresh();
        $this->assertTrue($user->hasEnabledTwoFactor());

        $this->getJson('/api/v1/user')
            ->assertOk()
            ->assertJsonPath('data.two_factor_enabled', true);
    }

    public function test_enable_requires_the_current_password(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/two-factor/enable')->assertStatus(422);
        $this->postJson('/api/v1/two-factor/enable', ['password' => 'wrong-password'])->assertStatus(422);

        $this->assertNull($user->refresh()->two_factor_secret);
    }

    public function test_enabling_again_cannot_strip_an_active_factor_without_the_password(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/two-factor/enable', ['password' => 'password'])->assertOk();
        $this->postJson('/api/v1/two-factor/confirm', ['code' => $this->currentOtp($user)])->assertNoContent();
        $this->assertTrue($user->refresh()->hasEnabledTwoFactor());

        // A hijacked session without the password cannot reset the active factor.
        $this->postJson('/api/v1/two-factor/enable')->assertStatus(422);
        $this->assertTrue($user->refresh()->hasEnabledTwoFactor());
    }

    public function test_confirmation_fails_with_an_invalid_code(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/two-factor/enable', ['password' => 'password'])->assertOk();

        $this->postJson('/api/v1/two-factor/confirm', ['code' => '000000'])
            ->assertStatus(422);
    }

    public function test_login_requires_a_code_when_two_factor_is_enabled(): void
    {
        $user = User::factory()->create([
            'email'    => 'john@example.com',
            'password' => 'super-secret-password',
        ]);
        Sanctum::actingAs($user);
        $this->postJson('/api/v1/two-factor/enable', ['password' => 'super-secret-password'])->assertOk();
        $user->refresh();
        $this->postJson('/api/v1/two-factor/confirm', ['code' => $this->currentOtp($user)])->assertNoContent();
        $user->refresh();

        $this->postJson('/api/v1/login', [
            'email'    => 'john@example.com',
            'password' => 'super-secret-password',
        ])->assertStatus(422);

        $this->postJson('/api/v1/login', [
            'email'    => 'john@example.com',
            'password' => 'super-secret-password',
            'code'     => $this->currentOtp($user),
        ])->assertOk()->assertJsonStructure(['data' => ['user', 'token', 'token_type'], 'message']);
    }

    public function test_a_recovery_code_can_be_used_to_login(): void
    {
        $user = User::factory()->create([
            'email'    => 'john@example.com',
            'password' => 'super-secret-password',
        ]);
        Sanctum::actingAs($user);
        $this->postJson('/api/v1/two-factor/enable', ['password' => 'super-secret-password'])->assertOk();
        $user->refresh();
        $this->postJson('/api/v1/two-factor/confirm', ['code' => $this->currentOtp($user)])->assertNoContent();
        $user->refresh();

        $recoveryCode = ($user->two_factor_recovery_codes ?? [])[0] ?? '';

        $this->postJson('/api/v1/login', [
            'email'    => 'john@example.com',
            'password' => 'super-secret-password',
            'code'     => $recoveryCode,
        ])->assertOk();
    }

    public function test_two_factor_can_be_disabled_with_the_current_password(): void
    {
        $user = User::factory()->create(['password' => 'secret-password-123']);
        Sanctum::actingAs($user);
        $this->postJson('/api/v1/two-factor/enable', ['password' => 'secret-password-123'])->assertOk();

        $this->deleteJson('/api/v1/two-factor', ['password' => 'wrong-password'])
            ->assertStatus(422);

        $this->deleteJson('/api/v1/two-factor', ['password' => 'secret-password-123'])
            ->assertNoContent();

        $user->refresh();
        $this->assertNull($user->two_factor_secret);
        $this->assertFalse($user->hasEnabledTwoFactor());
    }

    public function test_recovery_codes_can_be_regenerated_only_when_two_factor_is_enabled(): void
    {
        $user = User::factory()->create(['password' => 'secret-password-123']);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/two-factor/recovery-codes', ['password' => 'secret-password-123'])
            ->assertStatus(409);

        $this->postJson('/api/v1/two-factor/enable', ['password' => 'secret-password-123'])->assertOk();
        $user->refresh();
        $this->postJson('/api/v1/two-factor/confirm', ['code' => $this->currentOtp($user)])
            ->assertNoContent();
        $user->refresh();
        $originalCodes = $user->two_factor_recovery_codes;

        $this->postJson('/api/v1/two-factor/recovery-codes', ['password' => 'wrong-password'])
            ->assertStatus(422);

        $codes = $this->postJson('/api/v1/two-factor/recovery-codes', ['password' => 'secret-password-123'])
            ->assertOk()
            ->assertJsonStructure(['data' => ['recovery_codes'], 'message'])
            ->json('data.recovery_codes');

        $this->assertCount(8, is_array($codes) ? $codes : []);
        $user->refresh();
        $this->assertNotEquals($originalCodes, $user->two_factor_recovery_codes);
    }

    public function test_a_totp_code_cannot_be_replayed_on_login(): void
    {
        $user = User::factory()->create([
            'email'    => 'replay@example.com',
            'password' => 'super-secret-password',
        ]);
        Sanctum::actingAs($user);
        $this->postJson('/api/v1/two-factor/enable', ['password' => 'super-secret-password'])->assertOk();
        $user->refresh();
        $this->postJson('/api/v1/two-factor/confirm', ['code' => $this->currentOtp($user)])->assertNoContent();
        $user->refresh();

        $code = $this->currentOtp($user);

        $this->postJson('/api/v1/login', [
            'email'    => 'replay@example.com',
            'password' => 'super-secret-password',
            'code'     => $code,
        ])->assertOk();

        $this->postJson('/api/v1/login', [
            'email'    => 'replay@example.com',
            'password' => 'super-secret-password',
            'code'     => $code,
        ])->assertStatus(422);
    }
}
