<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Enrollment;
use App\Notifications\AnnualFeeDue;
use App\Services\StatementService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

class SendBillingReminders extends Command
{
    protected $description = 'Send tuition and annual-fee reminders 5 days before and 1 day after their due dates';

    protected $signature = 'notifications:send-billing-reminders';

    public function __construct(private StatementService $statements)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $preDate = today()->addDays(5);
        $overdueDate = today()->subDay();

        $this->statements->sendForCycle('upcoming', $preDate);
        $this->statements->sendForCycle('overdue', $overdueDate);

        $this->remindAnnualFee($preDate, overdue: false);
        $this->remindAnnualFee($overdueDate, overdue: true);

        return self::SUCCESS;
    }

    private function remindAnnualFee(Carbon $date, bool $overdue): void
    {
        Enrollment::query()
            ->with('user')
            ->where('annual_fee_is_paid', false)
            ->where('annual_fee', '>', 0)
            ->where('annual_fee_due_date', $date->toDateString())
            ->whereHas('user', fn (Builder $query): Builder => $query->where('is_active', true))
            ->chunkById(200, function (Collection $enrollments) use ($overdue): void {
                foreach ($enrollments as $enrollment) {
                    $enrollment->user->notify(new AnnualFeeDue($enrollment, $overdue));
                }
            });
    }
}
