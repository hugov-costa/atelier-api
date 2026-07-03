<?php

declare(strict_types=1);

namespace App\Console\Commands\Concerns;

use Closure;
use Illuminate\Database\Eloquent\Builder;

trait PrunesInBatches
{
    /**
     * Delete matching rows in bounded batches instead of a single statement, so a
     * large backlog never turns into a long-running transaction (locks, WAL spikes
     * and table bloat). Portable across drivers (no DELETE ... LIMIT).
     *
     * @param  Closure(): Builder<covariant \Illuminate\Database\Eloquent\Model>  $freshQuery  Returns a new constrained query each call.
     */
    protected function deleteInBatches(Closure $freshQuery, int $batchSize): int
    {
        $batchSize = max(1, $batchSize);
        $total = 0;

        do {
            $ids = $freshQuery()->limit($batchSize)->pluck('id');

            if ($ids->isEmpty()) {
                break;
            }

            $freshQuery()->whereIn('id', $ids)->delete();
            $total += $ids->count();
        } while ($ids->count() === $batchSize);

        return $total;
    }
}
