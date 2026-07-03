<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Impersonation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PruneImpersonationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_prunes_impersonations_older_than_the_retention_window(): void
    {
        $master = User::factory()->master()->create();
        $target = User::factory()->create();

        $old = Impersonation::create([
            'impersonator_id' => $master->id,
            'impersonated_id' => $target->id,
            'reason'          => 'old support visit',
            'expires_at'      => now()->subDays(199),
            'ended_at'        => now()->subDays(199),
        ]);
        DB::table('impersonations')->where('id', $old->id)->update([
            'created_at' => now()->subDays(200),
        ]);

        $recent = Impersonation::create([
            'impersonator_id' => $master->id,
            'impersonated_id' => $target->id,
            'reason'          => 'recent support visit',
            'expires_at'      => now()->addMinutes(30),
        ]);

        $this->assertSame(0, Artisan::call('impersonations:prune'));

        $this->assertSame(0, Impersonation::query()->whereKey($old->id)->count());
        $this->assertSame(1, Impersonation::query()->whereKey($recent->id)->count());
    }
}
