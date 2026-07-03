<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\DateOnly;
use App\Enums\PaymentMethod;
use App\Models\Concerns\HasUlidRouteKey;
use App\Models\Concerns\InvalidatesQueryCache;
use Database\Factories\MaterialPurchaseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * A procurement/receipt record for raw materials (clay or glaze). The latest
 * received purchase becomes the material's current unit price; already-produced
 * pieces keep their own price snapshot.
 *
 * @property int $id
 * @property string $ulid
 * @property int $material_id
 * @property int $supplier_id
 * @property string|null $description
 * @property int $freight
 * @property string|null $invoice_number
 * @property string|null $lot
 * @property string $material_type
 * @property PaymentMethod $payment_method
 * @property Carbon $purchase_date
 * @property float $quantity
 * @property Carbon|null $receipt_date
 * @property string $supplier_type
 * @property int $total_price
 * @property int $unit_price
 * @property Carbon|null $created_at
 * @property Carbon|null $deleted_at
 * @property Carbon|null $updated_at
 * @property-read Model|null $material
 * @property-read Model|null $supplier
 */
class MaterialPurchase extends Model implements Auditable
{
    use AuditableTrait;

    /** @use HasFactory<MaterialPurchaseFactory> */
    use HasFactory, HasUlidRouteKey, InvalidatesQueryCache, SoftDeletes;

    /**
     * @return array<int, string>|null
     */
    protected static function queryCacheColumns(): ?array
    {
        return ['material_type', 'material_id', 'supplier_type', 'supplier_id', 'purchase_date', 'deleted_at'];
    }

    /**
     * Polymorphic aliases for the material relation — the single source of truth for
     * the slug<->class mapping (registered as a morph map so `material_type` stores
     * the stable alias, not a class name).
     *
     * @var array<string, class-string<Clay|Glaze>>
     */
    public const MATERIAL_TYPES = [
        'clay'  => Clay::class,
        'glaze' => Glaze::class,
    ];

    /**
     * Polymorphic aliases for the supplier relation.
     *
     * @var array<string, class-string<ClaySupplier|GlazeSupplier>>
     */
    public const SUPPLIER_TYPES = [
        'clay_supplier'  => ClaySupplier::class,
        'glaze_supplier' => GlazeSupplier::class,
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'material_id',
        'supplier_id',
        'description',
        'freight',
        'invoice_number',
        'lot',
        'material_type',
        'payment_method',
        'purchase_date',
        'quantity',
        'receipt_date',
        'supplier_type',
        'total_price',
        'unit_price',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'freight'        => 'integer',
            'payment_method' => PaymentMethod::class,
            'purchase_date'  => DateOnly::class,
            'quantity'       => 'float',
            'receipt_date'   => DateOnly::class,
            'total_price'    => 'integer',
            'unit_price'     => 'integer',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function material(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function supplier(): MorphTo
    {
        return $this->morphTo();
    }
}
