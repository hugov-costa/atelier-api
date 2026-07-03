<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\DateOnly;
use App\Models\Concerns\HasUlidRouteKey;
use App\Models\Concerns\InvalidatesQueryCache;
use Database\Factories\PieceChargeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * A student's receivable for a piece they produced. Billed on a tuition cycle and
 * settled together with that tuition.
 *
 * @property int $id
 * @property string $ulid
 * @property int $piece_id
 * @property int|null $tuition_fee_id
 * @property int $user_id
 * @property int $amount
 * @property Carbon $due_date
 * @property Carbon|null $paid_at
 * @property Carbon|null $created_at
 * @property Carbon|null $deleted_at
 * @property Carbon|null $updated_at
 * @property-read Piece $piece
 * @property-read User $user
 * @property-read TuitionFee|null $tuitionFee
 */
class PieceCharge extends Model implements Auditable
{
    use AuditableTrait;

    /** @use HasFactory<PieceChargeFactory> */
    use HasFactory, HasUlidRouteKey, InvalidatesQueryCache, SoftDeletes;

    /**
     * @return array<int, string>|null
     */
    protected static function queryCacheColumns(): ?array
    {
        return ['piece_id', 'tuition_fee_id', 'user_id', 'amount', 'due_date', 'deleted_at'];
    }

    /**
     * @var list<string>
     */
    protected $fillable = [
        'piece_id',
        'tuition_fee_id',
        'user_id',
        'amount',
        'due_date',
        'paid_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'piece_id'       => 'integer',
            'tuition_fee_id' => 'integer',
            'user_id'        => 'integer',
            'amount'         => 'integer',
            'due_date'       => DateOnly::class,
            'paid_at'        => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Piece, $this>
     */
    public function piece(): BelongsTo
    {
        return $this->belongsTo(Piece::class);
    }

    /**
     * @return BelongsTo<TuitionFee, $this>
     */
    public function tuitionFee(): BelongsTo
    {
        return $this->belongsTo(TuitionFee::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
