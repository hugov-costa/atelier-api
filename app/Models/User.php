<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\UserRole;
use App\Models\Concerns\InvalidatesQueryCache;
use App\Notifications\ResetPasswordQueued;
use App\Notifications\VerifyEmailQueued;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * @property int $id
 * @property string $ulid
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string|null $password
 * @property Carbon|null $admission_date
 * @property Carbon|null $birthday
 * @property string|null $phone
 * @property bool $is_active
 * @property UserRole $role
 * @property string|null $avatar_path
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property string|null $two_factor_secret
 * @property array<int, string>|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property int|null $two_factor_last_used_timestep
 */
#[Fillable(['name', 'email', 'password', 'admission_date', 'birthday', 'phone', 'is_active'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes'])]
class User extends Authenticatable implements Auditable, MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use AuditableTrait, HasApiTokens, HasFactory, InvalidatesQueryCache, Notifiable, SoftDeletes;

    /**
     * Default attribute values applied to new instances.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'role'      => UserRole::User->value,
        'is_active' => true,
    ];

    /**
     * Attributes excluded from auditing.
     *
     * @var array<int, string>
     */
    protected $auditExclude = ['password', 'two_factor_secret', 'two_factor_recovery_codes'];

    protected static function booted(): void
    {
        static::creating(function (User $user): void {
            if (empty($user->ulid)) {
                $user->ulid = (string) Str::ulid();
            }
        });
    }

    /**
     * Use the public ULID for route-model binding instead of the integer key.
     */
    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'admission_date'            => 'date',
            'birthday'                  => 'date',
            'email_verified_at'         => 'datetime',
            'is_active'                 => 'boolean',
            'password'                  => 'hashed',
            'role'                      => UserRole::class,
            'two_factor_confirmed_at'   => 'datetime',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_secret'         => 'encrypted',
        ];
    }

    /**
     * @return array<int, string>
     */
    protected static function queryCacheColumns(): ?array
    {
        return ['name', 'email', 'role', 'avatar_path', 'email_verified_at', 'two_factor_confirmed_at', 'deleted_at'];
    }

    public function hasEnabledTwoFactor(): bool
    {
        return $this->two_factor_secret !== null && $this->two_factor_confirmed_at !== null;
    }

    public function isMaster(): bool
    {
        return $this->role === UserRole::Master;
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function canViewUsers(): bool
    {
        return $this->role->canViewOthers();
    }

    public function canManageUsers(): bool
    {
        return $this->role->canManageOthers();
    }

    /**
     * Enrollments held by the user as a student.
     *
     * @return HasMany<Enrollment, $this>
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    /**
     * Pieces produced by the user.
     *
     * @return HasMany<Piece, $this>
     */
    public function pieces(): HasMany
    {
        return $this->hasMany(Piece::class);
    }

    /**
     * One-off classes the user attends.
     *
     * @return BelongsToMany<SingleClass, $this>
     */
    public function singleClasses(): BelongsToMany
    {
        return $this->belongsToMany(SingleClass::class);
    }

    /**
     * Recurring classes the user attends.
     *
     * @return BelongsToMany<RecurrentClass, $this>
     */
    public function recurrentClasses(): BelongsToMany
    {
        return $this->belongsToMany(RecurrentClass::class);
    }

    /**
     * Send the queued email verification notification.
     *
     * @return void
     */
    public function sendEmailVerificationNotification()
    {
        $this->notify(new VerifyEmailQueued);
    }

    /**
     * Send the queued password reset notification.
     *
     * @param  string  $token
     * @return void
     */
    public function sendPasswordResetNotification($token)
    {
        $this->notify(new ResetPasswordQueued($token));
    }
}
