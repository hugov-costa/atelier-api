<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\CommissionOrder;
use App\Models\Piece;
use App\Services\CommissionOrderService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CommissionOrder
 */
class CommissionOrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $service = app(CommissionOrderService::class);
        /** @var CommissionOrder $order */
        $order = $this->resource;
        $customer = $this->customer;

        return [
            'id'         => $this->ulid,
            'created_at' => $this->created_at,
            'customer'   => $customer !== null
                ? ['id' => $customer->ulid, 'name' => $customer->name]
                : null,
            'delivery_date' => $this->delivery_date?->toDateString(),
            'description'   => $this->description,
            'is_paid'       => $this->paid_at !== null,
            'order_date'    => $this->order_date->toDateString(),
            'paid_at'       => $this->paid_at,
            'pieces'        => $this->whenLoaded('pieces', fn () => $this->pieces->map(fn (Piece $piece): array => [
                'id'              => $piece->ulid,
                'name'            => $piece->name,
                'price'           => $piece->price,
                'production_cost' => $piece->production_cost,
            ])->all()),
            'pieces_total'          => $service->piecesSaleTotal($order),
            'production_cost_total' => $service->productionCostTotal($order),
            'realized_margin'       => $service->realizedMargin($order),
            'sale_total'            => $service->effectiveSaleTotal($order),
            'sale_total_override'   => $this->sale_total_override,
            'shipping_charged'      => $this->shipping_charged,
            'shipping_cost'         => $this->shipping_cost,
            'status'                => $this->status->value,
            'updated_at'            => $this->updated_at,
        ];
    }
}
