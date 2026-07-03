<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Impersonation;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class ImpersonationService
{
    /**
     * Start impersonating the target user on behalf of the master.
     *
     * @return array{token: string, impersonation: Impersonation}
     */
    public function start(User $master, User $target, string $reason, ?string $ip): array
    {
        if ($master->is($target)) {
            throw ValidationException::withMessages([
                'user' => ['You cannot impersonate yourself.'],
            ]);
        }

        if ($target->isMaster()) {
            abort(Response::HTTP_FORBIDDEN, 'A master account cannot be impersonated.');
        }

        $minutes = $this->ttlMinutes();
        $expiresAt = now()->addMinutes($minutes);

        $newToken = $target->createToken($this->tokenName(), ['impersonate'], $expiresAt);

        $impersonation = Impersonation::create([
            'impersonator_id' => $master->getKey(),
            'impersonated_id' => $target->getKey(),
            'token_id'        => $newToken->accessToken->getKey(),
            'reason'          => $reason,
            'ip_address'      => $ip,
            'expires_at'      => $expiresAt,
        ]);

        return [
            'token'         => $newToken->plainTextToken,
            'impersonation' => $impersonation,
        ];
    }

    /**
     * Stop the impersonation tied to the current access token.
     */
    public function stop(User $current): void
    {
        $token = $current->currentAccessToken();

        if (! $token instanceof PersonalAccessToken) {
            return;
        }

        Impersonation::query()
            ->where('token_id', $token->getKey())
            ->whereNull('ended_at')
            ->update(['ended_at' => now()]);

        $token->delete();
    }

    /**
     * Resolve the active impersonation record for a given access token, if any.
     */
    public function activeFor(PersonalAccessToken $token): ?Impersonation
    {
        if ($token->name !== $this->tokenName()) {
            return null;
        }

        return Impersonation::query()
            ->active()
            ->where('token_id', $token->getKey())
            ->first();
    }

    public function ttlMinutes(): int
    {
        $minutes = config('auth.impersonation.ttl_minutes', 30);

        return is_numeric($minutes) && (int) $minutes > 0 ? (int) $minutes : 30;
    }

    private function tokenName(): string
    {
        $name = config('auth.impersonation.token_name', 'impersonation');

        return is_string($name) && $name !== '' ? $name : 'impersonation';
    }
}
