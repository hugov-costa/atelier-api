<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasUlidRouteKey;
use App\Models\Concerns\InvalidatesQueryCache;
use Database\Factories\GlazeSupplierFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $ulid
 * @property string $email
 * @property string $name
 * @property string $phone
 * @property Carbon|null $created_at
 * @property Carbon|null $deleted_at
 * @property Carbon|null $updated_at
 */
class GlazeSupplier extends Model
{
    /** @use HasFactory<GlazeSupplierFactory> */
    use HasFactory, HasUlidRouteKey, InvalidatesQueryCache, SoftDeletes;

    /**
     * @return array<int, string>|null
     */
    protected static function queryCacheColumns(): ?array
    {
        return ['name', 'email', 'deleted_at'];
    }

    /**
     * @var list<string>
     */
    protected $fillable = [
        'email',
        'name',
        'phone',
    ];

    /**
     * Soft-deleting a supplier cascades to its glazes; restoring brings them back.
     */
    protected static function booted(): void
    {
        static::deleting(function (GlazeSupplier $supplier): void {
            if ($supplier->isForceDeleting()) {
                return;
            }

            $supplier->glazes()->get()->each(fn (Glaze $glaze) => $glaze->delete());
        });

        static::restoring(function (GlazeSupplier $supplier): void {
            Glaze::onlyTrashed()->where('glaze_supplier_id', $supplier->id)->get()
                ->each(fn (Glaze $glaze) => $glaze->restore());
        });
    }

    /**
     * @return HasMany<Glaze, $this>
     */
    public function glazes(): HasMany
    {
        return $this->hasMany(Glaze::class);
    }
}
