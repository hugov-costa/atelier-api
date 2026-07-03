<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Commands\Concerns\PrunesInBatches;
use Illuminate\Console\Command;
use OwenIt\Auditing\Models\Audit;

class PruneAudits extends Command
{
    use PrunesInBatches;

    protected $signature = 'audit:prune {--days= : Override the retention window in days} '
        .'{--batch=1000 : Rows deleted per batch}';

    protected $description = 'Delete audit records older than the configured retention window';

    public function handle(): int
    {
        $days = $this->resolveDays();
        $cutoff = now()->subDays($days);

        $count = $this->deleteInBatches(
            fn () => Audit::query()->where('created_at', '<', $cutoff),
            $this->resolveBatchSize(),
        );

        $this->info(sprintf('Pruned %d audit record(s) older than %d day(s).', $count, $days));

        return self::SUCCESS;
    }

    private function resolveDays(): int
    {
        $option = $this->option('days');

        if (is_numeric($option)) {
            return max(1, (int) $option);
        }

        $configured = config('audit.retention_days', 180);

        return max(1, is_numeric($configured) ? (int) $configured : 180);
    }

    private function resolveBatchSize(): int
    {
        $option = $this->option('batch');

        return is_numeric($option) ? max(1, (int) $option) : 1000;
    }
}
