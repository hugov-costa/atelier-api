<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\DateOnly;
use App\Models\Concerns\HasUlidRouteKey;
use App\Models\Concerns\InvalidatesQueryCache;
use Database\Factories\BillFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * A recorded expense of the atelier.
 *
 * @property int $id
 * @property string $ulid
 * @property string|null $description
 * @property Carbon $due_date
 * @property bool $is_recurrent
 * @property string $name
 * @property int $reference_month
 * @property int $reference_year
 * @property int $value
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Bill extends Model implements Auditable
{
    use AuditableTrait;

    /** @use HasFactory<BillFactory> */
    use HasFactory, HasUlidRouteKey, InvalidatesQueryCache, SoftDeletes;

    /**
     * @return array<int, string>|null
     */
    protected static function queryCacheColumns(): ?array
    {
        return ['name', 'description', 'value', 'reference_month', 'reference_year', 'is_recurrent', 'due_date', 'deleted_at'];
    }

    /**
     * @var list<string>
     */
    protected $fillable = [
        'description',
        'due_date',
        'is_recurrent',
        'name',
        'reference_month',
        'reference_year',
        'value',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'due_date'        => DateOnly::class,
            'is_recurrent'    => 'boolean',
            'reference_month' => 'integer',
            'reference_year'  => 'integer',
            'value'           => 'integer',
        ];
    }
}
