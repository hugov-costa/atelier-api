<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class AccountService
{
    private const AVATAR_DISK = 'minio_public';

    /**
     * @var array<int, string>
     */
    private const REDACTED_AUDIT_KEYS = ['admission_date', 'birthday', 'email', 'name', 'phone'];

    private const REDACTION_PLACEHOLDER = '[redacted]';

    public function __construct(private AuthService $auth) {}

    /**
     * Irreversibly erase a user's personal data and soft-delete the account.
     *
     * Anonymises name, email, password, phone, birthday, admission_date, avatar,
     * two-factor secrets, role, and audit trail values. Used for a self-service
     * erasure request and for a master fulfilling a data subject's erasure request
     * on their behalf.
     */
    public function erase(User $user): void
    {
        $this->anonymize($user);

        $user->delete();
    }

    /**
     * Strip the identifying data from an account without deleting the row.
     *
     * Fields are anonymised (rather than hard-deleting) so audit/accountability records
     * that reference the account stay referentially valid while no longer identifying the
     * person. The scrub itself is not audited, so the old personal data is not copied into
     * a fresh audit record. Avatar removal is best-effort: a storage outage must never
     * block the erasure of the personal data held in the database.
     */
    public function anonymize(User $user): void
    {
        $this->auth->revokeAllTokens($user);

        $avatarPath = $user->avatar_path;

        User::withoutAuditing(function () use ($user): void {
            $user->forceFill([
                'admission_date'                => null,
                'birthday'                      => null,
                'name'                          => 'Conta removida',
                'email'                         => 'deleted-'.$user->ulid.'@deleted.invalid',
                'password'                      => Hash::make(Str::random(64)),
                'phone'                         => null,
                'avatar_path'                   => null,
                'email_verified_at'             => null,
                'two_factor_secret'             => null,
                'two_factor_recovery_codes'     => null,
                'two_factor_confirmed_at'       => null,
                'two_factor_last_used_timestep' => null,
                'role'                          => UserRole::User->value,
            ])->save();
        });

        $this->redactAuditTrail($user);

        if (is_string($avatarPath) && $avatarPath !== '') {
            try {
                Storage::disk(self::AVATAR_DISK)->delete($avatarPath);
            } catch (Throwable $exception) {
                Log::warning('Failed to delete avatar during account anonymisation.', [
                    'path'  => $avatarPath,
                    'error' => $exception->getMessage(),
                ]);
            }
        }
    }

    /**
     * Strip identifying values that were captured in the account's own audit
     * records before anonymisation. Without this the previous name and email
     * would survive in audits.old_values/new_values until the retention prune,
     * defeating the erasure for the duration of that window.
     */
    private function redactAuditTrail(User $user): void
    {
        $user->audits()
            ->where(fn ($query) => $query->whereNotNull('old_values')->orWhereNotNull('new_values'))
            ->chunkById(200, function ($audits): void {
                foreach ($audits as $audit) {
                    $audit->setAttribute('old_values', $this->scrubValues($audit->getAttribute('old_values')));
                    $audit->setAttribute('new_values', $this->scrubValues($audit->getAttribute('new_values')));
                    $audit->save();
                }
            });
    }

    /**
     * @param  mixed  $values
     * @return mixed
     */
    private function scrubValues($values)
    {
        if (! is_array($values)) {
            return $values;
        }

        foreach (self::REDACTED_AUDIT_KEYS as $key) {
            if (array_key_exists($key, $values)) {
                $values[$key] = self::REDACTION_PLACEHOLDER;
            }
        }

        return $values;
    }
}
