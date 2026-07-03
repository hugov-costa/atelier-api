<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use OwenIt\Auditing\Models\Audit;
use Tests\TestCase;

class PruneAuditsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_prunes_audits_older_than_the_retention_window(): void
    {
        $old = User::factory()->create();
        DB::table('audits')->update(['created_at' => now()->subDays(200)]);

        $recent = User::factory()->create();

        $this->assertSame(0, Artisan::call('audit:prune'));

        $this->assertSame(0, Audit::query()->where('auditable_id', $old->id)->count());
        $this->assertGreaterThan(0, Audit::query()->where('auditable_id', $recent->id)->count());
    }

    public function test_it_prunes_in_batches_smaller_than_the_backlog(): void
    {
        User::factory()->count(5)->create();
        DB::table('audits')->update(['created_at' => now()->subDays(200)]);

        $this->assertSame(0, Artisan::call('audit:prune', ['--batch' => 2]));

        $this->assertSame(0, Audit::query()->count());
    }
}
