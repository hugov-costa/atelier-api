<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasUlidRouteKey;
use App\Models\Concerns\InvalidatesQueryCache;
use Database\Factories\ClaySupplierFactory;
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
class ClaySupplier extends Model
{
    /** @use HasFactory<ClaySupplierFactory> */
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
     * Soft-deleting a supplier cascades to its clays; restoring brings them back.
     */
    protected static function booted(): void
    {
        static::deleting(function (ClaySupplier $supplier): void {
            if ($supplier->isForceDeleting()) {
                return;
            }

            $supplier->clays()->get()->each(fn (Clay $clay) => $clay->delete());
        });

        static::restoring(function (ClaySupplier $supplier): void {
            Clay::onlyTrashed()->where('clay_supplier_id', $supplier->id)->get()
                ->each(fn (Clay $clay) => $clay->restore());
        });
    }

    /**
     * @return HasMany<Clay, $this>
     */
    public function clays(): HasMany
    {
        return $this->hasMany(Clay::class);
    }
}
