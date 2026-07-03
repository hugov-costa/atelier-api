<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Setting;
use App\Services\LogoService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * @mixin Setting
 */
class SettingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'annual_enrollment_cost' => $this->annual_enrollment_cost,
            'base_cost'              => $this->base_cost,
            'clay_amount_multiplier' => $this->clay_amount_multiplier,
            'default_profit_margin'  => $this->default_profit_margin,
            'logo_url'               => $this->logo_path !== null
                ? Storage::disk(LogoService::DISK)->url($this->logo_path)
                : null,
            'piece_charge_billing_grace_days' => $this->piece_charge_billing_grace_days,
            'tuition_fee_due_day_of_month'    => $this->tuition_fee_due_day_of_month,
            'tuition_monthly_cost'            => $this->tuition_monthly_cost,
            'updated_at'                      => $this->updated_at,
        ];
    }
}
