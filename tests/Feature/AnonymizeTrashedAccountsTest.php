<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class AnonymizeTrashedAccountsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_anonymizes_accounts_soft_deleted_beyond_the_window(): void
    {
        $old = User::factory()->create(['email' => 'old@example.com']);
        $old->delete();
        User::withTrashed()->whereKey($old->id)->update(['deleted_at' => now()->subDays(40)]);

        $recent = User::factory()->create(['email' => 'recent@example.com']);
        $recent->delete();

        $this->assertSame(0, Artisan::call('accounts:anonymize-trashed'));

        $oldAfter = User::withTrashed()->findOrFail($old->id);
        $this->assertStringEndsWith('@deleted.invalid', $oldAfter->email);
        $this->assertSame('Conta removida', $oldAfter->name);

        $recentAfter = User::withTrashed()->findOrFail($recent->id);
        $this->assertSame('recent@example.com', $recentAfter->email);
    }

    public function test_it_skips_already_anonymized_accounts(): void
    {
        $erased = User::factory()->create();
        $erased->delete();
        User::withTrashed()->whereKey($erased->id)->update([
            'email'      => 'deleted-'.$erased->ulid.'@deleted.invalid',
            'name'       => 'Conta removida',
            'deleted_at' => now()->subDays(40),
        ]);

        $this->assertSame(0, Artisan::call('accounts:anonymize-trashed'));

        $this->assertSame(0, User::onlyTrashed()->where('email', 'not like', '%@deleted.invalid')->count());
    }
}
