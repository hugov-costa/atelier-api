<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\TuitionFeeService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class GenerateTuitionFees extends Command
{
    protected $description = "Create the current month's tuition fees for active, non-exempt enrollments";

    protected $signature = 'tuitions:generate';

    public function handle(TuitionFeeService $service): int
    {
        $count = $service->generateForMonth(Carbon::now());

        $this->info(sprintf('Generated %d tuition fee(s) for the current month.', $count));

        return self::SUCCESS;
    }
}
