<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\BillService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class GenerateRecurrentBills extends Command
{
    protected $description = 'Carry recurrent bills into the current month';

    protected $signature = 'bills:generate-recurrent';

    public function handle(BillService $service): int
    {
        $count = $service->generateRecurrentForMonth(Carbon::now());

        $this->info(sprintf('Created %d recurrent bill(s) for the current month.', $count));

        return self::SUCCESS;
    }
}
