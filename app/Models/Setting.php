<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Singleton configuration for the atelier (pricing and billing defaults).
 *
 * @property int $id
 * @property int $annual_enrollment_cost
 * @property int $base_cost
 * @property float $clay_amount_multiplier
 * @property float $default_profit_margin
 * @property string|null $logo_path
 * @property int $piece_charge_billing_grace_days
 * @property int $tuition_fee_due_day_of_month
 * @property int $tuition_monthly_cost
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Setting extends Model implements Auditable
{
    use AuditableTrait;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'annual_enrollment_cost',
        'base_cost',
        'clay_amount_multiplier',
        'default_profit_margin',
        'logo_path',
        'piece_charge_billing_grace_days',
        'tuition_fee_due_day_of_month',
        'tuition_monthly_cost',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'annual_enrollment_cost'          => 'integer',
            'base_cost'                       => 'integer',
            'clay_amount_multiplier'          => 'float',
            'default_profit_margin'           => 'float',
            'piece_charge_billing_grace_days' => 'integer',
            'tuition_fee_due_day_of_month'    => 'integer',
            'tuition_monthly_cost'            => 'integer',
        ];
    }

    /**
     * The application keeps exactly one settings row.
     */
    public static function current(): self
    {
        return self::query()->firstOrFail();
    }
}
