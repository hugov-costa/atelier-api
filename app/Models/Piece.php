<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PieceKind;
use App\Models\Concerns\HasUlidRouteKey;
use App\Models\Concerns\InvalidatesQueryCache;
use Database\Factories\PieceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use LogicException;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * A ceramic piece produced in the atelier. Its price and production cost are
 * always computed by the pricing service, never supplied by the client.
 *
 * @property int $id
 * @property string $ulid
 * @property int $clay_id
 * @property int|null $commission_order_id
 * @property int|null $glaze_id
 * @property int|null $piece_category_id
 * @property int $user_id
 * @property int $base_cost
 * @property float $clay_amount
 * @property int $clay_unit_price
 * @property float|null $glaze_amount
 * @property int|null $glaze_unit_price
 * @property PieceKind $kind
 * @property string $name
 * @property int $price
 * @property int $production_cost
 * @property float|null $profit_margin
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Clay $clay
 * @property-read Glaze|null $glaze
 * @property-read PieceCategory|null $category
 * @property-read User $user
 * @property-read CommissionOrder|null $commissionOrder
 */
class Piece extends Model implements Auditable
{
    use AuditableTrait;

    /** @use HasFactory<PieceFactory> */
    use HasFactory, HasUlidRouteKey, InvalidatesQueryCache, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'clay_id',
        'commission_order_id',
        'glaze_id',
        'piece_category_id',
        'user_id',
        'base_cost',
        'clay_amount',
        'clay_unit_price',
        'glaze_amount',
        'glaze_unit_price',
        'kind',
        'name',
        'price',
        'production_cost',
        'profit_margin',
    ];

    /**
     * Composition and pricing columns frozen at creation. A piece is a one-off
     * physical object, so re-pricing it would rewrite history; only administrative
     * fields (name) and its commission-order membership may change afterwards.
     *
     * @var list<string>
     */
    private const IMMUTABLE = [
        'clay_id',
        'glaze_id',
        'piece_category_id',
        'user_id',
        'kind',
        'clay_amount',
        'glaze_amount',
        'clay_unit_price',
        'glaze_unit_price',
        'base_cost',
        'profit_margin',
        'price',
        'production_cost',
    ];

    /**
     * @return array<int, string>|null
     */
    protected static function queryCacheColumns(): ?array
    {
        return ['name', 'clay_id', 'glaze_id', 'piece_category_id', 'user_id', 'kind', 'commission_order_id', 'deleted_at'];
    }

    protected static function booted(): void
    {
        static::updating(function (Piece $piece): void {
            $changed = array_intersect(self::IMMUTABLE, array_keys($piece->getDirty()));

            if ($changed !== []) {
                throw new LogicException(
                    'Piece composition and pricing are immutable; cannot change: '.implode(', ', $changed)
                );
            }
        });

        static::deleted(function (Piece $piece): void {
            if ($piece->isForceDeleting()) {
                return;
            }

            $piece->charge()->get()->each(fn (PieceCharge $charge) => $charge->delete());
        });

        static::restoring(function (Piece $piece): void {
            $deletedAt = $piece->deleted_at;

            PieceCharge::onlyTrashed()
                ->where('piece_id', $piece->id)
                ->when(
                    $deletedAt !== null,
                    fn (Builder $query): Builder => $query->where('deleted_at', '>=', $deletedAt),
                )
                ->get()
                ->each(fn (PieceCharge $charge) => $charge->restore());
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'clay_id'             => 'integer',
            'commission_order_id' => 'integer',
            'glaze_id'            => 'integer',
            'piece_category_id'   => 'integer',
            'user_id'             => 'integer',
            'base_cost'           => 'integer',
            'clay_amount'         => 'float',
            'clay_unit_price'     => 'integer',
            'glaze_amount'        => 'float',
            'glaze_unit_price'    => 'integer',
            'kind'                => PieceKind::class,
            'price'               => 'integer',
            'production_cost'     => 'integer',
            'profit_margin'       => 'float',
        ];
    }

    /**
     * @return BelongsTo<PieceCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(PieceCategory::class, 'piece_category_id');
    }

    /**
     * @return HasOne<PieceCharge, $this>
     */
    public function charge(): HasOne
    {
        return $this->hasOne(PieceCharge::class);
    }

    /**
     * @return BelongsTo<Clay, $this>
     */
    public function clay(): BelongsTo
    {
        return $this->belongsTo(Clay::class);
    }

    /**
     * @return BelongsTo<CommissionOrder, $this>
     */
    public function commissionOrder(): BelongsTo
    {
        return $this->belongsTo(CommissionOrder::class, 'commission_order_id');
    }

    /**
     * @return BelongsToMany<FiringCycle, $this>
     */
    public function firingCycles(): BelongsToMany
    {
        return $this->belongsToMany(FiringCycle::class, 'firing_cycle_piece')
            ->withPivot('price')
            ->withTimestamps();
    }

    /**
     * @return BelongsTo<Glaze, $this>
     */
    public function glaze(): BelongsTo
    {
        return $this->belongsTo(Glaze::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
