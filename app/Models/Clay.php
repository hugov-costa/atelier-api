<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasUlidRouteKey;
use App\Models\Concerns\InvalidatesQueryCache;
use Database\Factories\ClayFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $ulid
 * @property int $clay_supplier_id
 * @property string|null $description
 * @property string $name
 * @property int $price
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read ClaySupplier $claySupplier
 */
class Clay extends Model
{
    /** @use HasFactory<ClayFactory> */
    use HasFactory, HasUlidRouteKey, InvalidatesQueryCache, SoftDeletes;

    /**
     * @return array<int, string>|null
     */
    protected static function queryCacheColumns(): ?array
    {
        return ['name', 'clay_supplier_id', 'price', 'deleted_at'];
    }

    /**
     * @var list<string>
     */
    protected $fillable = [
        'clay_supplier_id',
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
            'clay_supplier_id' => 'integer',
            'price'            => 'integer',
        ];
    }

    /**
     * @return BelongsTo<ClaySupplier, $this>
     */
    public function claySupplier(): BelongsTo
    {
        return $this->belongsTo(ClaySupplier::class);
    }
}
