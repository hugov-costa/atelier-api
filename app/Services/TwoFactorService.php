<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorService
{
    public function __construct(private Google2FA $engine) {}

    /**
     * Provision a fresh (unconfirmed) two-factor secret and recovery codes.
     *
     * @return array{secret: string, qr_code_url: string, recovery_codes: array<int, string>}
     */
    public function enable(User $user): array
    {
        $secret = $this->engine->generateSecretKey();
        $recoveryCodes = $this->generateRecoveryCodes();

        $user->forceFill([
            'two_factor_secret'         => $secret,
            'two_factor_recovery_codes' => $recoveryCodes,
            'two_factor_confirmed_at'   => null,
        ])->save();

        return [
            'secret'         => $secret,
            'qr_code_url'    => $this->engine->getQRCodeUrl($this->company(), $user->email, $secret),
            'recovery_codes' => $recoveryCodes,
        ];
    }

    public function confirm(User $user, string $code): bool
    {
        $secret = $user->two_factor_secret;

        if ($secret === null || ! $this->engine->verifyKey($secret, $code)) {
            return false;
        }

        $user->forceFill(['two_factor_confirmed_at' => now()])->save();

        return true;
    }

    /**
     * Issue a fresh set of recovery codes, invalidating the previous ones.
     *
     * @return array<int, string>
     */
    public function regenerateRecoveryCodes(User $user): array
    {
        $codes = $this->generateRecoveryCodes();

        $user->forceFill(['two_factor_recovery_codes' => $codes])->save();

        return $codes;
    }

    public function disable(User $user): void
    {
        $user->forceFill([
            'two_factor_secret'         => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at'   => null,
        ])->save();
    }

    public function verify(User $user, string $code): bool
    {
        if ($user->two_factor_secret !== null && $this->verifyTotp($user, $code)) {
            return true;
        }

        return $this->consumeRecoveryCode($user, $code);
    }

    /**
     * Verify a TOTP code, rejecting any code from a time-step already consumed so a
     * code cannot be replayed within its validity window.
     */
    private function verifyTotp(User $user, string $code): bool
    {
        $secret = $user->two_factor_secret;

        if ($secret === null) {
            return false;
        }

        $timestamp = $this->engine->verifyKeyNewer($secret, $code, $user->two_factor_last_used_timestep ?? 0);

        if ($timestamp === false) {
            return false;
        }

        $user->forceFill(['two_factor_last_used_timestep' => (int) $timestamp])->save();

        return true;
    }

    private function consumeRecoveryCode(User $user, string $code): bool
    {
        $codes = $user->two_factor_recovery_codes ?? [];

        $matched = false;
        $remaining = [];

        foreach ($codes as $stored) {
            if (hash_equals($stored, $code)) {
                $matched = true;

                continue;
            }

            $remaining[] = $stored;
        }

        if (! $matched) {
            return false;
        }

        $user->forceFill([
            'two_factor_recovery_codes' => $remaining,
        ])->save();

        return true;
    }

    /**
     * @return array<int, string>
     */
    private function generateRecoveryCodes(): array
    {
        return Collection::times(8, fn (): string => Str::random(10).'-'.Str::random(10))->all();
    }

    private function company(): string
    {
        $name = config('app.name');

        return is_string($name) ? $name : 'atelie';
    }
}
