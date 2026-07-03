<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\User;
use Tests\TestCase;

class UserTest extends TestCase
{
    public function test_two_factor_is_only_enabled_with_both_a_secret_and_a_confirmation(): void
    {
        $user = new User;
        $this->assertFalse($user->hasEnabledTwoFactor());

        $user->two_factor_secret = 'secret';
        $this->assertFalse($user->hasEnabledTwoFactor());

        $user->two_factor_confirmed_at = now();
        $this->assertTrue($user->hasEnabledTwoFactor());
    }
}
