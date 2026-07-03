<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasUlidRouteKey;
use App\Models\Concerns\InvalidatesQueryCache;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A person or business the atelier fulfils commission orders for. Reusable
 * across many orders and kept as a lightweight contact record.
 *
 * @property int $id
 * @property string $ulid
 * @property string|null $description
 * @property string|null $email
 * @property string $name
 * @property string|null $phone
 * @property Carbon|null $created_at
 * @property Carbon|null $deleted_at
 * @property Carbon|null $updated_at
 */
class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory, HasUlidRouteKey, InvalidatesQueryCache, SoftDeletes;

    /**
     * @return array<int, string>|null
     */
    protected static function queryCacheColumns(): ?array
    {
        return ['name', 'email', 'phone', 'deleted_at'];
    }

    /**
     * @var list<string>
     */
    protected $fillable = [
        'description',
        'email',
        'name',
        'phone',
    ];

    /**
     * Soft-deleting a customer cascades to its orders; restoring brings them back.
     */
    protected static function booted(): void
    {
        static::deleting(function (Customer $customer): void {
            if ($customer->isForceDeleting()) {
                return;
            }

            $customer->orders()->get()->each(fn (CommissionOrder $order) => $order->delete());
        });

        static::restoring(function (Customer $customer): void {
            CommissionOrder::onlyTrashed()->where('customer_id', $customer->id)->get()
                ->each(fn (CommissionOrder $order) => $order->restore());
        });
    }

    /**
     * @return HasMany<CommissionOrder, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(CommissionOrder::class);
    }
}
