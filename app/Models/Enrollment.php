<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\DateOnly;
use App\Models\Concerns\HasUlidRouteKey;
use App\Models\Concerns\InvalidatesQueryCache;
use Database\Factories\EnrollmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * A student's enrollment in the atelier.
 *
 * @property int $id
 * @property string $ulid
 * @property int $user_id
 * @property int $annual_fee
 * @property Carbon|null $annual_fee_due_date
 * @property bool $annual_fee_is_paid
 * @property Carbon|null $annual_fee_paid_at
 * @property bool $is_exempt_from_annual_fee
 * @property bool $is_exempt_from_piece_charges
 * @property bool $is_exempt_from_tuition_fee
 * @property Carbon|null $created_at
 * @property Carbon|null $deleted_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 */
class Enrollment extends Model implements Auditable
{
    use AuditableTrait;

    /** @use HasFactory<EnrollmentFactory> */
    use HasFactory, HasUlidRouteKey, InvalidatesQueryCache, SoftDeletes;

    /**
     * @return array<int, string>|null
     */
    protected static function queryCacheColumns(): ?array
    {
        return ['user_id', 'annual_fee_is_paid', 'is_exempt_from_annual_fee', 'is_exempt_from_piece_charges', 'is_exempt_from_tuition_fee', 'deleted_at'];
    }

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'annual_fee',
        'annual_fee_due_date',
        'annual_fee_is_paid',
        'annual_fee_paid_at',
        'is_exempt_from_annual_fee',
        'is_exempt_from_piece_charges',
        'is_exempt_from_tuition_fee',
    ];

    /**
     * Soft-deleting an enrollment cascades to its tuition fees; restoring brings them back.
     */
    protected static function booted(): void
    {
        static::deleting(function (Enrollment $enrollment): void {
            if ($enrollment->isForceDeleting()) {
                return;
            }

            $enrollment->tuitionFees()->get()->each(fn (TuitionFee $fee) => $fee->delete());
        });

        static::restoring(function (Enrollment $enrollment): void {
            TuitionFee::onlyTrashed()->where('enrollment_id', $enrollment->id)->get()
                ->each(fn (TuitionFee $fee) => $fee->restore());
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'user_id'                      => 'integer',
            'annual_fee'                   => 'integer',
            'annual_fee_due_date'          => DateOnly::class,
            'annual_fee_is_paid'           => 'boolean',
            'annual_fee_paid_at'           => 'datetime',
            'is_exempt_from_annual_fee'    => 'boolean',
            'is_exempt_from_piece_charges' => 'boolean',
            'is_exempt_from_tuition_fee'   => 'boolean',
        ];
    }

    /**
     * @return HasMany<TuitionFee, $this>
     */
    public function tuitionFees(): HasMany
    {
        return $this->hasMany(TuitionFee::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
