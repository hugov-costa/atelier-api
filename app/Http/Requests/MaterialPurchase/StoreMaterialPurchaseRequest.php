<?php

declare(strict_types=1);

namespace App\Http\Requests\MaterialPurchase;

use App\Enums\PaymentMethod;
use App\Models\Clay;
use App\Models\ClaySupplier;
use App\Models\Glaze;
use App\Models\GlazeSupplier;
use App\Models\MaterialPurchase;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMaterialPurchaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', MaterialPurchase::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /**
             * Free-text notes about the purchase.
             *
             * @example Compra mensal de argila
             */
            'description' => ['nullable', 'string', 'max:1000'],

            /**
             * Freight/shipping portion of the total, in cents. Defaults to 0.
             *
             * @example 2500
             */
            'freight' => ['sometimes', 'integer', 'min:0', 'max:99999999'],

            /**
             * Invoice number.
             *
             * @example NF-000123
             */
            'invoice_number' => ['nullable', 'string', 'max:255'],

            /**
             * Supplier lot/batch identifier.
             *
             * @example LOTE-2026-03
             */
            'lot' => ['nullable', 'string', 'max:255'],

            /**
             * Public id (ULID) of the clay or glaze being purchased.
             *
             * @example 01J9Z3K7QffEXAMPLEULID0000
             */
            'material_id' => ['required', 'string'],

            /**
             * Kind of material being purchased.
             *
             * @example clay
             */
            'material_type' => ['required', Rule::in(['clay', 'glaze'])],

            /**
             * How the purchase was paid.
             *
             * @example pix
             */
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],

            /**
             * Date the purchase was placed (YYYY-mm-dd).
             *
             * @example 2026-03-01
             */
            'purchase_date' => ['required', 'date'],

            /**
             * Quantity purchased (kilograms of clay or number of jars).
             *
             * @example 25
             */
            'quantity' => ['required', 'numeric', 'min:0.001'],

            /**
             * Date the goods physically arrived (YYYY-mm-dd). Cannot precede the purchase date.
             *
             * @example 2026-03-05
             */
            'receipt_date' => ['nullable', 'date', 'after_or_equal:purchase_date'],

            /**
             * Public id (ULID) of the supplier. Defaults to the material's own supplier when omitted.
             *
             * @example 01J9Z3K7QffEXAMPLEULID0000
             */
            'supplier_id' => ['nullable', 'string'],

            /**
             * Authoritative total in cents (may include freight/discount).
             *
             * @example 30000
             */
            'total_price' => ['required', 'integer', 'min:0', 'max:99999999'],

            /**
             * Unit price in cents (per kg of clay or per jar of glaze).
             *
             * @example 1200
             */
            'unit_price' => ['required', 'integer', 'min:0', 'max:99999999'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $materialType = $this->input('material_type');
            $materialId = $this->input('material_id');

            if (! is_string($materialType) || ! is_string($materialId)) {
                return;
            }

            $material = match ($materialType) {
                'clay'  => Clay::query()->where('ulid', $materialId)->first(),
                'glaze' => Glaze::query()->where('ulid', $materialId)->first(),
                default => null,
            };

            if ($material === null) {
                $validator->errors()->add('material_id', 'The selected material does not exist.');

                return;
            }

            $supplierId = $this->input('supplier_id');

            if (! is_string($supplierId) || $supplierId === '') {
                return;
            }

            // The supplier must be the material's own supplier, otherwise a purchase
            // (and per-supplier spend reports) could be attributed to an unrelated one.
            $ownsSupplier = $material instanceof Clay
                ? ClaySupplier::query()->whereKey($material->clay_supplier_id)->where('ulid', $supplierId)->exists()
                : GlazeSupplier::query()->whereKey($material->glaze_supplier_id)->where('ulid', $supplierId)->exists();

            if (! $ownsSupplier) {
                $validator->errors()->add('supplier_id', 'The selected supplier does not supply this material.');
            }
        });
    }
}
