<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasUlidRouteKey;
use App\Models\Concerns\InvalidatesQueryCache;
use Database\Factories\RecurrentClassFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A weekly recurring class slot.
 *
 * @property int $id
 * @property string $ulid
 * @property int $day_of_the_week
 * @property string $end_time
 * @property string $start_time
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class RecurrentClass extends Model
{
    /** @use HasFactory<RecurrentClassFactory> */
    use HasFactory, HasUlidRouteKey, InvalidatesQueryCache, SoftDeletes;

    /**
     * @return array<int, string>|null
     */
    protected static function queryCacheColumns(): ?array
    {
        return ['day_of_the_week', 'start_time', 'end_time', 'deleted_at'];
    }

    /**
     * @var list<string>
     */
    protected $fillable = [
        'day_of_the_week',
        'end_time',
        'start_time',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'day_of_the_week' => 'integer',
        ];
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }
}
