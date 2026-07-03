<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\DateOnly;
use App\Models\Concerns\HasUlidRouteKey;
use App\Models\Concerns\InvalidatesQueryCache;
use Database\Factories\PieceCategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $ulid
 * @property Carbon|null $available_until
 * @property string $name
 * @property float $profit_margin
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class PieceCategory extends Model
{
    /** @use HasFactory<PieceCategoryFactory> */
    use HasFactory, HasUlidRouteKey, InvalidatesQueryCache, SoftDeletes;

    /**
     * @return array<int, string>|null
     */
    protected static function queryCacheColumns(): ?array
    {
        return ['name', 'profit_margin', 'available_until', 'deleted_at'];
    }

    /**
     * @var list<string>
     */
    protected $fillable = [
        'available_until',
        'name',
        'profit_margin',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'available_until' => DateOnly::class,
            'profit_margin'   => 'float',
        ];
    }

    /**
     * Whether the category is still offered as of today.
     */
    public function isAvailable(): bool
    {
        return $this->available_until === null || $this->available_until->endOfDay()->isFuture();
    }

    /**
     * @return HasMany<Piece, $this>
     */
    public function pieces(): HasMany
    {
        return $this->hasMany(Piece::class);
    }
}
