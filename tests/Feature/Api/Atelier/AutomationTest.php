<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Atelier;

use App\Models\Bill;
use App\Models\Enrollment;
use App\Models\Setting;
use App\Models\TuitionFee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class AutomationTest extends TestCase
{
    use RefreshDatabase;

    public function test_tuition_generation_is_idempotent_and_skips_exempt_and_inactive(): void
    {
        Setting::current()->update(['tuition_monthly_cost' => 20000, 'tuition_fee_due_day_of_month' => 10]);

        Enrollment::factory()->for(User::factory()->create())->create(['is_exempt_from_tuition_fee' => false]);
        Enrollment::factory()->for(User::factory()->create())->create(['is_exempt_from_tuition_fee' => true]);
        Enrollment::factory()
            ->for(User::factory()->create(['is_active' => false]))
            ->create(['is_exempt_from_tuition_fee' => false]);

        $this->assertSame(0, Artisan::call('tuitions:generate'));
        $this->assertSame(1, TuitionFee::count());
        $this->assertSame(20000, TuitionFee::firstOrFail()->amount);

        Artisan::call('tuitions:generate');
        $this->assertSame(1, TuitionFee::count());
    }

    public function test_recurrent_bills_are_carried_into_the_current_month(): void
    {
        $lastMonth = now()->subMonthNoOverflow();

        Bill::factory()->create([
            'name'            => 'Aluguel',
            'is_recurrent'    => true,
            'value'           => 300000,
            'reference_year'  => (int) $lastMonth->format('Y'),
            'reference_month' => (int) $lastMonth->format('n'),
            'due_date'        => $lastMonth->copy()->day(5)->toDateString(),
        ]);
        Bill::factory()->create(['name' => 'Compra avulsa', 'is_recurrent' => false]);

        $this->assertSame(0, Artisan::call('bills:generate-recurrent'));

        $this->assertDatabaseHas('bills', [
            'name'            => 'Aluguel',
            'reference_year'  => (int) now()->format('Y'),
            'reference_month' => (int) now()->format('n'),
            'value'           => 300000,
        ]);

        Artisan::call('bills:generate-recurrent');
        $this->assertSame(2, Bill::where('name', 'Aluguel')->count());
    }

    public function test_recurrent_generation_does_not_resurrect_a_deleted_bill(): void
    {
        $lastMonth = now()->subMonthNoOverflow();

        Bill::factory()->create([
            'name'            => 'Aluguel',
            'is_recurrent'    => true,
            'value'           => 300000,
            'reference_year'  => (int) $lastMonth->format('Y'),
            'reference_month' => (int) $lastMonth->format('n'),
            'due_date'        => $lastMonth->copy()->day(5)->toDateString(),
        ]);

        Artisan::call('bills:generate-recurrent');

        $thisYear = (int) now()->format('Y');
        $thisMonth = (int) now()->format('n');

        Bill::query()
            ->where('name', 'Aluguel')
            ->where('reference_year', $thisYear)
            ->where('reference_month', $thisMonth)
            ->firstOrFail()
            ->delete();

        // Re-running must respect the deletion, not silently recreate the expense.
        Artisan::call('bills:generate-recurrent');

        $this->assertSame(0, Bill::query()
            ->where('name', 'Aluguel')
            ->where('reference_year', $thisYear)
            ->where('reference_month', $thisMonth)
            ->count());
    }
}
