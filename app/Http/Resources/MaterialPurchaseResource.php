<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\MaterialPurchase;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin MaterialPurchase
 */
class MaterialPurchaseResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $material = $this->material;
        $supplier = $this->supplier;

        return [
            'id'             => $this->ulid,
            'created_at'     => $this->created_at,
            'description'    => $this->description,
            'freight'        => $this->freight,
            'invoice_number' => $this->invoice_number,
            'is_received'    => $this->receipt_date !== null,
            'lot'            => $this->lot,
            'material'       => [
                'id'   => $material?->getAttribute('ulid'),
                'name' => $material?->getAttribute('name'),
                'type' => $this->material_type,
            ],
            'payment_method' => $this->payment_method->value,
            'purchase_date'  => $this->purchase_date->toDateString(),
            'quantity'       => $this->quantity,
            'receipt_date'   => $this->receipt_date?->toDateString(),
            'supplier'       => [
                'id'   => $supplier?->getAttribute('ulid'),
                'name' => $supplier?->getAttribute('name'),
                'type' => $this->supplier_type,
            ],
            'total_price' => $this->total_price,
            'unit_price'  => $this->unit_price,
            'updated_at'  => $this->updated_at,
        ];
    }
}
