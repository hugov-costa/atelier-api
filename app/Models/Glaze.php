<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasUlidRouteKey;
use App\Models\Concerns\InvalidatesQueryCache;
use Database\Factories\GlazeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $ulid
 * @property int $glaze_supplier_id
 * @property string|null $description
 * @property string $name
 * @property int $price
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read GlazeSupplier $glazeSupplier
 */
class Glaze extends Model
{
    /** @use HasFactory<GlazeFactory> */
    use HasFactory, HasUlidRouteKey, InvalidatesQueryCache, SoftDeletes;

    /**
     * @return array<int, string>|null
     */
    protected static function queryCacheColumns(): ?array
    {
        return ['name', 'glaze_supplier_id', 'price', 'deleted_at'];
    }

    /**
     * @var list<string>
     */
    protected $fillable = [
        'glaze_supplier_id',
        'description',
        'name',
        'price',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'glaze_supplier_id' => 'integer',
            'price'             => 'integer',
        ];
    }

    /**
     * @return BelongsTo<GlazeSupplier, $this>
     */
    public function glazeSupplier(): BelongsTo
    {
        return $this->belongsTo(GlazeSupplier::class);
    }
}
