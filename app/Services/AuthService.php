<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Cookie;

class AuthService
{
    /**
     * A valid bcrypt hash checked when the account is missing or passwordless, so the
     * login path spends the same time whether or not the email exists (no enumeration
     * via response timing).
     */
    private const TIMING_GUARD_HASH = '$2y$12$fJvNQDVUkx.DssB7Y2Ucf.nxLNyx4InnIz.kn/NpvAvtYAYHZSQv6';

    public function __construct(private TwoFactorService $twoFactor) {}

    /**
     * Validate credentials (and the two-factor code when enabled).
     */
    public function authenticate(string $email, string $password, string $code = ''): User
    {
        $user = User::where('email', $email)->first();

        $hash = $user instanceof User && is_string($user->password)
            ? $user->password
            : self::TIMING_GUARD_HASH;

        $passwordMatches = Hash::check($password, $hash);

        if (! $user instanceof User || ! $passwordMatches) {
            throw ValidationException::withMessages([
                'email' => ['These credentials do not match our records.'],
            ]);
        }

        if ($user->hasEnabledTwoFactor() && ($code === '' || ! $this->twoFactor->verify($user, $code))) {
            throw ValidationException::withMessages([
                'code' => ['A valid two-factor authentication code is required.'],
            ]);
        }

        return $user;
    }

    public function issueToken(User $user): string
    {
        return $user->createToken('api')->plainTextToken;
    }

    public function revokeCurrentToken(User $user): void
    {
        $token = $user->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }
    }

    public function revokeAllTokens(User $user): void
    {
        $user->tokens()->delete();
    }

    /**
     * Set a new password and revoke every other token, keeping the current one.
     */
    public function changePassword(User $user, string $newPassword): void
    {
        $user->password = $newPassword;
        $user->save();

        $current = $user->currentAccessToken();
        $keepId = $current instanceof PersonalAccessToken ? $current->getKey() : null;

        $query = $user->tokens();

        if ($keepId !== null) {
            $query->where('id', '!=', $keepId);
        }

        $query->delete();
    }

    public function tokenCookie(string $token): Cookie
    {
        return cookie(
            name: $this->cookieName(),
            value: $token,
            minutes: $this->cookieLifetime(),
            path: '/',
            domain: null,
            secure: $this->cookieSecure(),
            httpOnly: true,
            raw: false,
            sameSite: $this->cookieSameSite(),
        );
    }

    public function csrfCookie(): Cookie
    {
        return cookie(
            name: $this->csrfCookieName(),
            value: Str::random(40),
            minutes: $this->cookieLifetime(),
            path: '/',
            domain: null,
            secure: $this->cookieSecure(),
            httpOnly: false,
            raw: false,
            sameSite: $this->cookieSameSite(),
        );
    }

    public function forgetTokenCookie(): Cookie
    {
        return cookie()->forget($this->cookieName());
    }

    public function forgetCsrfCookie(): Cookie
    {
        return cookie()->forget($this->csrfCookieName());
    }

    /**
     * The double-submit CSRF cookie is readable by the SPA (not httpOnly), so it
     * carries the __Host- prefix whenever it is issued as a secure cookie. The
     * prefix pins the cookie to this exact host with Path=/ and no Domain,
     * preventing a sibling subdomain from injecting a forged token. Plain HTTP
     * dev environments, where the cookie cannot be secure, fall back to the
     * unprefixed name.
     *
     * @return non-empty-string
     */
    private function csrfCookieName(): string
    {
        return $this->cookieSecure() ? '__Host-XSRF-TOKEN' : 'XSRF-TOKEN';
    }

    public function impersonationCookie(string $token, int $minutes): Cookie
    {
        return cookie(
            name: $this->impersonationCookieName(),
            value: $token,
            minutes: $minutes,
            path: '/',
            domain: null,
            secure: $this->cookieSecure(),
            httpOnly: true,
            raw: false,
            sameSite: $this->cookieSameSite(),
        );
    }

    public function forgetImpersonationCookie(): Cookie
    {
        return cookie()->forget($this->impersonationCookieName());
    }

    /**
     * @return non-empty-string
     */
    private function impersonationCookieName(): string
    {
        $name = config('auth.impersonation.cookie', 'impersonate_token');

        return is_string($name) && $name !== '' ? $name : 'impersonate_token';
    }

    /**
     * @return non-empty-string
     */
    private function cookieName(): string
    {
        $name = config('auth.token_cookie.name', 'access_token');

        return is_string($name) && $name !== '' ? $name : 'access_token';
    }

    private function cookieLifetime(): int
    {
        $lifetime = config('auth.token_cookie.lifetime', 60 * 24 * 14);

        return is_numeric($lifetime) ? (int) $lifetime : 60 * 24 * 14;
    }

    private function cookieSecure(): bool
    {
        return (bool) config('auth.token_cookie.secure', true);
    }

    private function cookieSameSite(): string
    {
        $sameSite = config('auth.token_cookie.same_site', 'lax');

        return is_string($sameSite) ? $sameSite : 'lax';
    }
}
