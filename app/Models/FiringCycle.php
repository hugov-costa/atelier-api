<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasUlidRouteKey;
use App\Models\Concerns\InvalidatesQueryCache;
use Database\Factories\FiringCycleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $ulid
 * @property int $cycle
 * @property int $duration
 * @property string $name
 * @property int $price_per_unit
 * @property float $temperature
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class FiringCycle extends Model
{
    /** @use HasFactory<FiringCycleFactory> */
    use HasFactory, HasUlidRouteKey, InvalidatesQueryCache, SoftDeletes;

    /**
     * @return array<int, string>|null
     */
    protected static function queryCacheColumns(): ?array
    {
        return ['name', 'cycle', 'price_per_unit', 'temperature', 'deleted_at'];
    }

    /**
     * @var list<string>
     */
    protected $fillable = [
        'cycle',
        'duration',
        'name',
        'price_per_unit',
        'temperature',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cycle'          => 'integer',
            'duration'       => 'integer',
            'price_per_unit' => 'integer',
            'temperature'    => 'float',
        ];
    }

    /**
     * @return BelongsToMany<Piece, $this>
     */
    public function pieces(): BelongsToMany
    {
        return $this->belongsToMany(Piece::class, 'firing_cycle_piece')
            ->withPivot('price')
            ->withTimestamps();
    }
}
