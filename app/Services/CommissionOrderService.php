<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PieceKind;
use App\Models\CommissionOrder;
use App\Models\Customer;
use App\Models\Piece;
use App\Support\Money;
use App\Support\Pagination;
use App\Support\PublicId;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class CommissionOrderService
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): CommissionOrder
    {
        $customerId = PublicId::resolve(Customer::class, $attributes['customer_id'] ?? null);

        /** @var array<int, string> $pieceIds */
        $pieceIds = is_array($attributes['piece_ids'] ?? null) ? $attributes['piece_ids'] : [];
        unset($attributes['piece_ids'], $attributes['customer_id']);

        $order = new CommissionOrder($attributes);
        $order->customer_id = $customerId;

        if (blank($order->status)) {
            $order->status = OrderStatus::Pending;
        }

        $order->save();

        $this->attachPieces($order, $pieceIds);

        return $order->load(['customer', 'pieces']);
    }

    public function delete(CommissionOrder $order): void
    {
        $order->delete();
    }

    /**
     * The full amount the customer pays: the pieces' value plus shipping charged.
     */
    public function effectiveSaleTotal(CommissionOrder $order): int
    {
        return $this->piecesSaleTotal($order) + Money::toInt($order->shipping_charged);
    }

    /**
     * @param  array{status?: string|null, customer_id?: string|null, paid?: string|null}  $filters
     * @return LengthAwarePaginator<int, CommissionOrder>
     */
    public function paginate(int $perPage, array $filters = []): LengthAwarePaginator
    {
        $status = $filters['status'] ?? null;
        $customerUlid = $filters['customer_id'] ?? null;
        $paid = $filters['paid'] ?? null;

        $customerId = $customerUlid !== null ? PublicId::resolve(Customer::class, $customerUlid) : null;

        return CommissionOrder::query()
            ->with(['customer', 'pieces'])
            ->when($status !== null, fn (Builder $query): Builder => $query->where('status', $status))
            ->when($customerId !== null, fn (Builder $query): Builder => $query->where('customer_id', $customerId))
            ->when($paid === 'paid', fn (Builder $query): Builder => $query->whereNotNull('paid_at'))
            ->when($paid === 'unpaid', fn (Builder $query): Builder => $query->whereNull('paid_at'))
            ->orderByDesc('order_date')
            ->paginate(Pagination::perPage($perPage));
    }

    /**
     * The pieces' sale value: the negotiated override, or the sum of piece prices.
     */
    public function piecesSaleTotal(CommissionOrder $order): int
    {
        return $order->sale_total_override ?? Money::toInt($order->pieces->sum('price'));
    }

    public function productionCostTotal(CommissionOrder $order): int
    {
        return Money::toInt($order->pieces->sum('production_cost'));
    }

    /**
     * Realized profit: what the customer pays (pieces + shipping charged) minus the
     * atelier's costs (production cost of the pieces + shipping actually paid).
     */
    public function realizedMargin(CommissionOrder $order): int
    {
        return $this->effectiveSaleTotal($order)
            - $this->productionCostTotal($order)
            - Money::toInt($order->shipping_cost);
    }

    public function restore(CommissionOrder $order): CommissionOrder
    {
        $order->restore();

        return $order->load(['customer', 'pieces']);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(array $attributes, CommissionOrder $order): CommissionOrder
    {
        $order->fill(array_intersect_key($attributes, array_flip([
            'status',
            'order_date',
            'delivery_date',
            'sale_total_override',
            'shipping_charged',
            'shipping_cost',
            'description',
        ])));

        if (array_key_exists('is_paid', $attributes)) {
            $isPaid = (bool) $attributes['is_paid'];

            if ($isPaid && $order->paid_at === null) {
                $order->paid_at = Carbon::now();
            } elseif (! $isPaid && $order->paid_at !== null) {
                $order->paid_at = null;
            }
        }

        $order->save();

        if (array_key_exists('piece_ids', $attributes)) {
            /** @var array<int, string> $pieceIds */
            $pieceIds = is_array($attributes['piece_ids']) ? $attributes['piece_ids'] : [];
            $this->syncPieces($order, $pieceIds);
        }

        return $order->load(['customer', 'pieces']);
    }

    /**
     * Attach the given commission pieces to the order, moving any that belonged
     * to another order.
     *
     * @param  array<int, string>  $pieceUlids
     */
    private function attachPieces(CommissionOrder $order, array $pieceUlids): void
    {
        foreach ($this->commissionPieces($pieceUlids) as $piece) {
            $piece->commission_order_id = $order->id;
            $piece->save();
        }
    }

    /**
     * Resolve the given ULIDs to existing commission pieces, defensively rejecting
     * any non-commission piece (the request layer returns the 422 first).
     *
     * @param  array<int, string>  $pieceUlids
     * @return Collection<int, Piece>
     */
    private function commissionPieces(array $pieceUlids): Collection
    {
        $ulids = array_values(array_filter($pieceUlids, 'is_string'));

        if ($ulids === []) {
            /** @var Collection<int, Piece> $empty */
            $empty = Piece::query()->whereRaw('1 = 0')->get();

            return $empty;
        }

        $pieces = Piece::query()->whereIn('ulid', $ulids)->get();

        foreach ($pieces as $piece) {
            if ($piece->kind !== PieceKind::Commission) {
                throw ValidationException::withMessages([
                    'piece_ids' => 'Every piece must be a commission piece.',
                ]);
            }
        }

        return $pieces;
    }

    /**
     * Replace the order's pieces with exactly the given set: newly listed pieces
     * are attached; pieces currently on the order but absent are detached.
     *
     * @param  array<int, string>  $pieceUlids
     */
    private function syncPieces(CommissionOrder $order, array $pieceUlids): void
    {
        $pieces = $this->commissionPieces($pieceUlids);
        $keepIds = $pieces->pluck('id')->all();

        Piece::query()
            ->where('commission_order_id', $order->id)
            ->when($keepIds !== [], fn (Builder $query): Builder => $query->whereNotIn('id', $keepIds))
            ->update(['commission_order_id' => null]);

        foreach ($pieces as $piece) {
            if ($piece->commission_order_id !== $order->id) {
                $piece->commission_order_id = $order->id;
                $piece->save();
            }
        }
    }
}
