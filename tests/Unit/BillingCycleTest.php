<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\BillingCycle;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;

class BillingCycleTest extends TestCase
{
    public function test_due_date_is_clamped_to_the_month_length(): void
    {
        $this->assertSame('2026-02-28', BillingCycle::dueDateForMonth(Carbon::parse('2026-02-15'), 31));
        $this->assertSame('2026-03-10', BillingCycle::dueDateForMonth(Carbon::parse('2026-03-20'), 10));
    }

    public function test_next_due_date_is_the_upcoming_cycle(): void
    {
        $this->assertSame('2026-01-10', BillingCycle::nextDueDate(10, Carbon::parse('2026-01-05')));
        $this->assertSame('2026-01-10', BillingCycle::nextDueDate(10, Carbon::parse('2026-01-10')));
        $this->assertSame('2026-02-10', BillingCycle::nextDueDate(10, Carbon::parse('2026-01-15')));
    }

    public function test_next_due_date_stays_on_the_due_day_regardless_of_time_of_day(): void
    {
        $this->assertSame('2026-01-10', BillingCycle::nextDueDate(10, Carbon::parse('2026-01-10 15:30:00')));
    }

    public function test_piece_charge_within_the_grace_window_bills_the_next_cycle(): void
    {
        $this->assertSame('2026-02-10', BillingCycle::pieceChargeDueDate(Carbon::parse('2026-01-15'), 10, 20));
        $this->assertSame('2026-02-10', BillingCycle::pieceChargeDueDate(Carbon::parse('2026-01-10'), 10, 20));
        $this->assertSame('2026-02-10', BillingCycle::pieceChargeDueDate(Carbon::parse('2026-01-30'), 10, 20));
    }

    public function test_piece_charge_past_the_grace_window_is_pushed_one_cycle_further(): void
    {
        $this->assertSame('2026-03-10', BillingCycle::pieceChargeDueDate(Carbon::parse('2026-01-31'), 10, 20));
        $this->assertSame('2026-03-10', BillingCycle::pieceChargeDueDate(Carbon::parse('2026-02-02'), 10, 20));
        $this->assertSame('2026-03-10', BillingCycle::pieceChargeDueDate(Carbon::parse('2026-02-09'), 10, 20));
    }
}
