<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Atelier;

use App\Models\Bill;
use App\Models\CommissionOrder;
use App\Models\Enrollment;
use App\Models\MaterialPurchase;
use App\Models\Piece;
use App\Models\PieceCategory;
use App\Models\TuitionFee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_monthly_report_summarises_revenue_and_expenses(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $year = (int) now()->format('Y');
        $month = (int) now()->format('n');

        TuitionFee::factory()->create(['amount' => 20000, 'paid_at' => now()]);
        TuitionFee::factory()->create(['amount' => 20000, 'paid_at' => null, 'due_date' => now()->toDateString()]);
        Enrollment::factory()->create([
            'annual_fee'         => 30000,
            'annual_fee_is_paid' => true,
            'annual_fee_paid_at' => now(),
        ]);
        Enrollment::factory()->create([
            'annual_fee'         => 30000,
            'annual_fee_is_paid' => false,
            'annual_fee_paid_at' => null,
        ]);
        Bill::factory()->create(['value' => 5000, 'reference_year' => $year, 'reference_month' => $month]);
        MaterialPurchase::factory()->create(['total_price' => 7000, 'purchase_date' => now()->toDateString()]);

        $this->getJson("/api/v1/reports/monthly?year={$year}&month={$month}")
            ->assertOk()
            ->assertJsonPath('data.revenue.tuition_paid', 20000)
            ->assertJsonPath('data.revenue.annual_fees_paid', 30000)
            ->assertJsonPath('data.revenue.total', 50000)
            ->assertJsonPath('data.expenses.bills_total', 5000)
            ->assertJsonPath('data.expenses.materials_total', 7000)
            ->assertJsonPath('data.expenses.total', 12000)
            ->assertJsonPath('data.net', 38000)
            ->assertJsonPath('data.tuition.paid_count', 1)
            ->assertJsonPath('data.tuition.unpaid_count', 1);
    }

    public function test_report_includes_commission_sales_margin_and_category_margins(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $year = (int) now()->format('Y');
        $month = (int) now()->format('n');

        $category = PieceCategory::factory()->create(['name' => 'Tigelas']);
        $p1 = Piece::factory()->create([
            'piece_category_id' => $category->id,
            'price'             => 10000,
            'production_cost'   => 6000,
        ]);
        $p2 = Piece::factory()->create([
            'piece_category_id' => $category->id,
            'price'             => 8000,
            'production_cost'   => 5000,
        ]);

        $order = CommissionOrder::factory()->paid()->create(['shipping_charged' => 2000, 'shipping_cost' => 500]);
        $p1->update(['commission_order_id' => $order->id]);
        $p2->update(['commission_order_id' => $order->id]);

        $this->getJson("/api/v1/reports/monthly?year={$year}&month={$month}")
            ->assertOk()
            ->assertJsonPath('data.revenue.commission_sales', 20000)
            ->assertJsonPath('data.commission.orders_paid_count', 1)
            ->assertJsonPath('data.commission.shipping_cost', 500)
            ->assertJsonPath('data.commission.realized_margin', 8500)
            ->assertJsonPath('data.expenses.commission_shipping', 500)
            ->assertJsonPath('data.net', 19500)
            ->assertJsonPath('data.production.by_category.0.category', 'Tigelas')
            ->assertJsonPath('data.production.by_category.0.count', 2)
            ->assertJsonPath('data.production.by_category.0.margin', 7000);
    }

    public function test_active_students_count_excludes_staff_and_inactive_users(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        User::factory()->count(2)->create();
        User::factory()->master()->create();
        User::factory()->create(['is_active' => false]);

        $this->getJson('/api/v1/reports/monthly')
            ->assertOk()
            ->assertJsonPath('data.counts.active_students', 2);
    }

    public function test_students_cannot_view_reports(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/reports/monthly')->assertForbidden();
    }
}
