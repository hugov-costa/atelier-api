<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Services\AccountService;
use Illuminate\Console\Command;

class AnonymizeTrashedAccounts extends Command
{
    protected $signature = 'accounts:anonymize-trashed {--days= : Override the soft-delete window in days}';

    protected $description = 'Anonymise the personal data of accounts soft-deleted beyond the retention window';

    public function handle(AccountService $accounts): int
    {
        $days = $this->resolveDays();
        $cutoff = now()->subDays($days);
        $count = 0;

        User::onlyTrashed()
            ->where('deleted_at', '<', $cutoff)
            ->where('email', 'not like', '%@deleted.invalid')
            ->chunkById(100, function ($users) use ($accounts, &$count): void {
                foreach ($users as $user) {
                    $accounts->anonymize($user);
                    $count++;
                }
            });

        $this->info(sprintf('Anonymised %d account(s) soft-deleted more than %d day(s) ago.', $count, $days));

        return self::SUCCESS;
    }

    private function resolveDays(): int
    {
        $option = $this->option('days');

        if (is_numeric($option)) {
            return max(1, (int) $option);
        }

        $configured = config('auth.account.anonymize_trashed_days', 30);

        return max(1, is_numeric($configured) ? (int) $configured : 30);
    }
}
