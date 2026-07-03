<?php

declare(strict_types=1);

namespace App\Http\Requests\MaterialPurchase;

use App\Enums\PaymentMethod;
use App\Models\MaterialPurchase;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMaterialPurchaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        $purchase = $this->route('material_purchase');
        $actor = $this->user();

        return $purchase instanceof MaterialPurchase && $actor !== null && $actor->can('update', $purchase);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $purchase = $this->route('material_purchase');
        $purchaseDate = $this->has('purchase_date')
            ? $this->date('purchase_date')?->toDateString()
            : ($purchase instanceof MaterialPurchase ? $purchase->purchase_date->toDateString() : null);

        return [
            /**
             * Free-text notes about the purchase.
             *
             * @example Ajuste de valor com frete
             */
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],

            /**
             * Freight/shipping portion of the total, in cents. Defaults to 0.
             *
             * @example 2500
             */
            'freight' => ['sometimes', 'integer', 'min:0', 'max:99999999'],

            /**
             * Invoice number.
             *
             * @example NF-000124
             */
            'invoice_number' => ['sometimes', 'nullable', 'string', 'max:255'],

            /**
             * Supplier lot/batch identifier.
             *
             * @example LOTE-2026-04
             */
            'lot' => ['sometimes', 'nullable', 'string', 'max:255'],

            /**
             * How the purchase was paid.
             *
             * @example bank_transfer
             */
            'payment_method' => ['sometimes', Rule::enum(PaymentMethod::class)],

            /**
             * Date the purchase was placed (YYYY-mm-dd).
             *
             * @example 2026-04-01
             */
            'purchase_date' => ['sometimes', 'date'],

            /**
             * Quantity purchased (kilograms of clay or number of jars).
             *
             * @example 30
             */
            'quantity' => ['sometimes', 'numeric', 'min:0.001'],

            /**
             * Date the goods physically arrived (YYYY-mm-dd). Cannot precede the purchase date.
             *
             * @example 2026-04-05
             */
            'receipt_date' => $purchaseDate !== null
                ? ['sometimes', 'nullable', 'date', 'after_or_equal:'.$purchaseDate]
                : ['sometimes', 'nullable', 'date'],

            /**
             * Authoritative total in cents (may include freight/discount).
             *
             * @example 39000
             */
            'total_price' => ['sometimes', 'integer', 'min:0', 'max:99999999'],

            /**
             * Unit price in cents (per kg of clay or per jar of glaze).
             *
             * @example 1300
             */
            'unit_price' => ['sometimes', 'integer', 'min:0', 'max:99999999'],
        ];
    }
}
