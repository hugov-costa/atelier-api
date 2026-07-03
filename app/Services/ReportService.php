<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Bill;
use App\Models\CommissionOrder;
use App\Models\Enrollment;
use App\Models\MaterialPurchase;
use App\Models\Piece;
use App\Models\PieceCharge;
use App\Models\TuitionFee;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ReportService
{
    public function __construct(private CommissionOrderService $orders) {}

    /**
     * A month's financial and activity summary. All monetary values are in cents.
     *
     * @return array<string, mixed>
     */
    public function monthly(int $month, int $year): array
    {
        $start = Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $tuitionPaid = $this->tuitionPaidInWindow($end, $start);
        $annualFeesPaid = Money::toInt(
            Enrollment::query()->whereBetween('annual_fee_paid_at', [$start, $end])->sum('annual_fee')
        );
        $pieceChargesPaid = Money::toInt(
            PieceCharge::query()->whereBetween('paid_at', [$start, $end])->sum('amount')
        );

        $commission = $this->commissionSummary($end, $start);
        $commissionSales = Money::toInt($commission['sales']);
        $commissionShipping = Money::toInt($commission['shipping_cost']);

        $revenueTotal = $tuitionPaid['total'] + $annualFeesPaid + $pieceChargesPaid + $commissionSales;

        $billsTotal = Money::toInt(
            Bill::query()->where('reference_year', $year)->where('reference_month', $month)->sum('value')
        );
        $materialsTotal = Money::toInt(
            MaterialPurchase::query()
                ->whereBetween('purchase_date', [$start->toDateString(), $end->toDateString()])
                ->sum('total_price')
        );
        $expensesTotal = $billsTotal + $materialsTotal + $commissionShipping;

        $tuitionDue = $this->tuitionDueInWindow($end, $start);
        $byCategory = $this->marginByCategory($end, $start);
        $piecesProduced = array_sum(array_map(static fn (array $row): int => $row['count'], $byCategory));

        return [
            'period'  => ['year' => $year, 'month' => $month],
            'revenue' => [
                'tuition_paid'       => $tuitionPaid['total'],
                'annual_fees_paid'   => $annualFeesPaid,
                'piece_charges_paid' => $pieceChargesPaid,
                'commission_sales'   => $commissionSales,
                'total'              => $revenueTotal,
            ],
            'expenses' => [
                'bills_total'         => $billsTotal,
                'materials_total'     => $materialsTotal,
                'commission_shipping' => $commissionShipping,
                'total'               => $expensesTotal,
            ],
            'net'        => $revenueTotal - $expensesTotal,
            'commission' => $commission,
            'tuition'    => [
                'paid_count'   => $tuitionPaid['count'],
                'due_count'    => $tuitionDue['count'],
                'unpaid_count' => $tuitionDue['unpaid'],
            ],
            'production' => [
                'pieces_produced' => $piecesProduced,
                'by_category'     => $byCategory,
            ],
            'counts' => [
                'active_students' => User::query()->where('is_active', true)->count(),
                'new_enrollments' => Enrollment::query()->whereBetween('created_at', [$start, $end])->count(),
            ],
        ];
    }

    /**
     * Realized commission figures for orders paid within the window: the effective
     * sale total (negotiated override or the sum of piece prices), the production
     * cost of those pieces, and the realized margin.
     *
     * @return array{orders_paid_count: int, sales: int, production_cost: int, shipping_cost: int, realized_margin: int}
     */
    private function commissionSummary(Carbon $end, Carbon $start): array
    {
        $paidOrders = CommissionOrder::query()
            ->whereBetween('paid_at', [$start, $end])
            ->with('pieces')
            ->get();

        $sales = 0;
        $cost = 0;
        $shipping = 0;

        foreach ($paidOrders as $order) {
            $sales += $this->orders->effectiveSaleTotal($order);
            $cost += $this->orders->productionCostTotal($order);
            $shipping += Money::toInt($order->shipping_cost);
        }

        return [
            'orders_paid_count' => $paidOrders->count(),
            'sales'             => $sales,
            'production_cost'   => $cost,
            'shipping_cost'     => $shipping,
            'realized_margin'   => $sales - $cost - $shipping,
        ];
    }

    /**
     * Theoretical margin (price − production cost) of the pieces produced in the
     * window, grouped by category. A null category groups under `category: null`.
     *
     * @return list<array{category: string|null, count: int, price_total: int, production_cost_total: int, margin: int}>
     */
    private function marginByCategory(Carbon $end, Carbon $start): array
    {
        /** @var Collection<int, \stdClass> $rows */
        $rows = Piece::query()
            ->leftJoin('piece_categories', 'pieces.piece_category_id', '=', 'piece_categories.id')
            ->whereBetween('pieces.created_at', [$start, $end])
            ->groupBy('piece_categories.name')
            ->orderBy('piece_categories.name')
            ->selectRaw('piece_categories.name as category')
            ->selectRaw('COUNT(*) as row_count')
            ->selectRaw('SUM(pieces.price) as price_total')
            ->selectRaw('SUM(pieces.production_cost) as production_cost_total')
            ->toBase()
            ->get();

        return array_values($rows->map(static function (\stdClass $row): array {
            $priceTotal = Money::toInt($row->price_total);
            $productionCostTotal = Money::toInt($row->production_cost_total);

            return [
                'category'              => is_string($row->category) ? $row->category : null,
                'count'                 => Money::toInt($row->row_count),
                'price_total'           => $priceTotal,
                'production_cost_total' => $productionCostTotal,
                'margin'                => $priceTotal - $productionCostTotal,
            ];
        })->all());
    }

    /**
     * Tuition due in the window: total count and how many are still unpaid, in one
     * aggregate query (both keyed on due_date).
     *
     * @return array{count: int, unpaid: int}
     */
    private function tuitionDueInWindow(Carbon $end, Carbon $start): array
    {
        /** @var object{row_count: int|string, unpaid_count: int|string|null}|null $row */
        $row = TuitionFee::query()
            ->whereBetween('due_date', [$start->toDateString(), $end->toDateString()])
            ->selectRaw('COUNT(*) AS row_count, SUM(CASE WHEN paid_at IS NULL THEN 1 ELSE 0 END) AS unpaid_count')
            ->toBase()
            ->first();

        return [
            'count'  => Money::toInt($row?->row_count),
            'unpaid' => Money::toInt($row?->unpaid_count),
        ];
    }

    /**
     * Paid-tuition total and count in one aggregate query (both keyed on paid_at).
     *
     * @return array{total: int, count: int}
     */
    private function tuitionPaidInWindow(Carbon $end, Carbon $start): array
    {
        /** @var object{total_amount: int|string|null, row_count: int|string}|null $row */
        $row = TuitionFee::query()
            ->whereBetween('paid_at', [$start, $end])
            ->selectRaw('COALESCE(SUM(amount), 0) AS total_amount, COUNT(*) AS row_count')
            ->toBase()
            ->first();

        return [
            'total' => Money::toInt($row?->total_amount),
            'count' => Money::toInt($row?->row_count),
        ];
    }
}
