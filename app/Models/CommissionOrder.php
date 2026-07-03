<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\DateOnly;
use App\Enums\OrderStatus;
use App\Models\Concerns\HasUlidRouteKey;
use App\Models\Concerns\InvalidatesQueryCache;
use Database\Factories\CommissionOrderFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * A commissioned sale for a customer, composed of one or more commission pieces.
 * The sale total is derived from its pieces unless a negotiated override is set.
 *
 * @property int $id
 * @property string $ulid
 * @property int $customer_id
 * @property Carbon|null $delivery_date
 * @property string|null $description
 * @property Carbon $order_date
 * @property Carbon|null $paid_at
 * @property int|null $sale_total_override
 * @property int $shipping_charged
 * @property int $shipping_cost
 * @property OrderStatus $status
 * @property Carbon|null $created_at
 * @property Carbon|null $deleted_at
 * @property Carbon|null $updated_at
 * @property-read Customer $customer
 * @property-read Collection<int, Piece> $pieces
 */
class CommissionOrder extends Model implements Auditable
{
    use AuditableTrait;

    /** @use HasFactory<CommissionOrderFactory> */
    use HasFactory, HasUlidRouteKey, InvalidatesQueryCache, SoftDeletes;

    /**
     * @return array<int, string>|null
     */
    protected static function queryCacheColumns(): ?array
    {
        return ['customer_id', 'status', 'order_date', 'delivery_date', 'paid_at', 'deleted_at'];
    }

    /**
     * @var list<string>
     */
    protected $fillable = [
        'customer_id',
        'delivery_date',
        'description',
        'order_date',
        'paid_at',
        'sale_total_override',
        'shipping_charged',
        'shipping_cost',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'customer_id'         => 'integer',
            'delivery_date'       => DateOnly::class,
            'order_date'          => DateOnly::class,
            'paid_at'             => 'datetime',
            'sale_total_override' => 'integer',
            'shipping_charged'    => 'integer',
            'shipping_cost'       => 'integer',
            'status'              => OrderStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return HasMany<Piece, $this>
     */
    public function pieces(): HasMany
    {
        return $this->hasMany(Piece::class);
    }
}
