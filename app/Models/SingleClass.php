<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasUlidRouteKey;
use App\Models\Concerns\InvalidatesQueryCache;
use Database\Factories\SingleClassFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A one-off (or replacement) class on a specific date and time.
 *
 * @property int $id
 * @property string $ulid
 * @property Carbon $end_datetime
 * @property bool $is_replacement
 * @property int $price
 * @property Carbon $start_datetime
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class SingleClass extends Model
{
    /** @use HasFactory<SingleClassFactory> */
    use HasFactory, HasUlidRouteKey, InvalidatesQueryCache, SoftDeletes;

    /**
     * @return array<int, string>|null
     */
    protected static function queryCacheColumns(): ?array
    {
        return ['start_datetime', 'end_datetime', 'price', 'is_replacement', 'deleted_at'];
    }

    /**
     * @var list<string>
     */
    protected $fillable = [
        'end_datetime',
        'is_replacement',
        'price',
        'start_datetime',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'end_datetime'   => 'datetime',
            'is_replacement' => 'boolean',
            'price'          => 'integer',
            'start_datetime' => 'datetime',
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
