<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\DateOnly;
use App\Models\Concerns\HasUlidRouteKey;
use App\Models\Concerns\InvalidatesQueryCache;
use Database\Factories\TuitionFeeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * A single monthly tuition charge against an enrollment.
 *
 * @property int $id
 * @property string $ulid
 * @property int $enrollment_id
 * @property int $amount
 * @property Carbon $due_date
 * @property Carbon|null $paid_at
 * @property Carbon|null $created_at
 * @property Carbon|null $deleted_at
 * @property Carbon|null $updated_at
 * @property-read Enrollment $enrollment
 */
class TuitionFee extends Model implements Auditable
{
    use AuditableTrait;

    /** @use HasFactory<TuitionFeeFactory> */
    use HasFactory, HasUlidRouteKey, InvalidatesQueryCache, SoftDeletes;

    /**
     * @return array<int, string>|null
     */
    protected static function queryCacheColumns(): ?array
    {
        return ['enrollment_id', 'amount', 'due_date', 'deleted_at'];
    }

    /**
     * @var list<string>
     */
    protected $fillable = [
        'enrollment_id',
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
            'enrollment_id' => 'integer',
            'amount'        => 'integer',
            'due_date'      => DateOnly::class,
            'paid_at'       => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Enrollment, $this>
     */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }
}
