<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $impersonator_id
 * @property int $impersonated_id
 * @property int|null $token_id
 * @property string $reason
 * @property string|null $ip_address
 * @property Carbon $expires_at
 * @property Carbon|null $ended_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Impersonation extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'impersonated_id',
        'impersonator_id',
        'token_id',
        'ended_at',
        'expires_at',
        'ip_address',
        'reason',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ended_at'   => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function impersonator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'impersonator_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function impersonated(): BelongsTo
    {
        return $this->belongsTo(User::class, 'impersonated_id');
    }

    public function isActive(): bool
    {
        return $this->ended_at === null && $this->expires_at->isFuture();
    }

    /**
     * @param  Builder<Impersonation>  $query
     * @return Builder<Impersonation>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('ended_at')->where('expires_at', '>', now());
    }
}
