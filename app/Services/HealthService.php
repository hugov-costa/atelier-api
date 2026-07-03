<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class HealthService
{
    /**
     * @return array{status: string, checks: array<string, string>}
     */
    public function check(): array
    {
        $checks = [
            'database' => $this->probe(fn () => DB::connection()->getPdo()),
            'cache'    => $this->probe(fn () => Cache::store()->get('health-check')),
            'storage'  => $this->probe(fn () => Storage::disk('minio_public')->exists('.health')),
        ];

        $healthy = ! in_array('down', $checks, true);

        return [
            'status' => $healthy ? 'ok' : 'degraded',
            'checks' => $checks,
        ];
    }

    private function probe(callable $check): string
    {
        try {
            $check();

            return 'ok';
        } catch (Throwable $exception) {
            Log::warning('Health check dependency is unreachable', [
                'reason' => $exception->getMessage(),
            ]);

            return 'down';
        }
    }
}
