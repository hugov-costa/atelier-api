<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\MaterialPurchase;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Telescope\TelescopeServiceProvider as TelescopePackageServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        if ($this->app->environment('local') && class_exists(TelescopePackageServiceProvider::class)) {
            $this->app->register(TelescopePackageServiceProvider::class);
            $this->app->register(TelescopeServiceProvider::class);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Store stable slugs (e.g. "clay") in polymorphic *_type columns instead of
        // class names, and keep the slug<->class mapping in one place. Non-enforcing,
        // so models outside the map (User, etc.) keep their default class-name morph.
        Relation::morphMap([
            ...MaterialPurchase::MATERIAL_TYPES,
            ...MaterialPurchase::SUPPLIER_TYPES,
        ]);

        Gate::define(
            'viewPulse',
            fn (?User $user): bool => $this->app->environment('local') || ($user !== null && $user->isMaster()),
        );

        Gate::define('viewReports', fn (User $user): bool => $user->canViewUsers());

        Password::defaults(fn () => Password::min(8)->mixedCase()->numbers()->symbols());

        VerifyEmail::createUrlUsing(function (User $notifiable): string {
            $verifyUrl = URL::temporarySignedRoute('verification.verify', Carbon::now()->addMinutes(60), [
                'id'   => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ]);

            return $this->frontendUrl('/verify-email').'?verify_url='.urlencode($verifyUrl);
        });

        ResetPassword::createUrlUsing(function (mixed $notifiable, string $token): string {
            $email = $notifiable instanceof CanResetPassword ? $notifiable->getEmailForPasswordReset() : '';

            return $this->frontendUrl('/reset-password').'?token='.$token.'&email='.urlencode($email);
        });

        RateLimiter::for('api', function (Request $request): Limit {
            $user = $request->user();

            $key = $user instanceof User ? 'user:'.$user->id : 'ip:'.((string) $request->ip());

            return Limit::perMinute(60)->by($key);
        });

        RateLimiter::for('auth', /** @return list<Limit> */ function (Request $request): array {
            $email = $request->input('email');
            $ip = (string) $request->ip();
            $emailKey = is_string($email) && $email !== '' ? $email : 'unknown';

            return [
                Limit::perMinute(5)->by('auth|ip:'.$ip),
                Limit::perMinute(5)->by('auth|email:'.$emailKey.'|ip:'.$ip),
                // Pure per-email cap so a distributed (many-IP) brute force against a
                // single account is still throttled account-wide.
                Limit::perMinute(10)->by('auth|email:'.$emailKey),
            ];
        });
    }

    private function frontendUrl(string $path): string
    {
        $base = config('app.frontend_url');

        return (is_string($base) ? rtrim($base, '/') : '').$path;
    }
}
