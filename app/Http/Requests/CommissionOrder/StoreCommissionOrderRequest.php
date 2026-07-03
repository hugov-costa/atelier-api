<?php

declare(strict_types=1);

namespace App\Http\Requests\CommissionOrder;

use App\Enums\OrderStatus;
use App\Enums\PieceKind;
use App\Models\CommissionOrder;
use App\Models\Piece;
use App\Support\Rules;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCommissionOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', CommissionOrder::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /**
             * Public id (ULID) of the customer the order is for.
             *
             * @example 01J9Z3K7QffEXAMPLEULID0000
             */
            'customer_id' => ['required', 'string', Rules::existsActive('customers', 'ulid')],

            /**
             * Agreed delivery date (YYYY-mm-dd). Cannot precede the order date.
             *
             * @example 2026-04-01
             */
            'delivery_date' => ['nullable', 'date', 'after_or_equal:order_date'],

            /**
             * Free-text notes about the order.
             *
             * @example Conjunto de 6 xícaras personalizadas.
             */
            'description' => ['nullable', 'string', 'max:1000'],

            /**
             * Date the order was placed (YYYY-mm-dd).
             *
             * @example 2026-03-01
             */
            'order_date' => ['required', 'date'],

            /**
             * Public ids (ULIDs) of the commission pieces composing the order.
             */
            'piece_ids' => ['nullable', 'array', 'max:200'],

            /**
             * @example 01J9Z3K7QffEXAMPLEULID0000
             */
            'piece_ids.*' => ['string', 'distinct', Rules::existsActive('pieces', 'ulid')],

            /**
             * Negotiated total in cents that overrides the derived sum of piece prices.
             *
             * @example 50000
             */
            'sale_total_override' => ['nullable', 'integer', 'min:0', 'max:99999999'],

            /**
             * Shipping charged to the customer in cents; added to the sale total. Defaults to 0.
             *
             * @example 3000
             */
            'shipping_charged' => ['sometimes', 'integer', 'min:0', 'max:99999999'],

            /**
             * Shipping paid by the atelier in cents; reduces the realized margin. Defaults to 0.
             *
             * @example 2000
             */
            'shipping_cost' => ['sometimes', 'integer', 'min:0', 'max:99999999'],

            /**
             * Order status. Defaults to "pending" when omitted.
             *
             * @example pending
             */
            'status' => ['nullable', Rule::enum(OrderStatus::class)],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $pieceIds = $this->input('piece_ids');

            if (! is_array($pieceIds) || $pieceIds === []) {
                return;
            }

            $ulids = array_values(array_filter($pieceIds, 'is_string'));

            $commissionCount = Piece::query()
                ->whereIn('ulid', $ulids)
                ->where('kind', PieceKind::Commission->value)
                ->count();

            if ($commissionCount !== count(array_unique($ulids))) {
                $validator->errors()->add('piece_ids', 'Every piece must be a commission piece.');
            }
        });
    }
}
