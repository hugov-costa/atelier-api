<?php

declare(strict_types=1);

use App\Exceptions\ApiExceptionRenderer;
use App\Http\Middleware\AssignRequestId;
use App\Http\Middleware\AuthenticateFromCookie;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\EnsureUserCanViewUsers;
use App\Http\Middleware\RestrictImpersonatedSession;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\VerifyCookieCsrfToken;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Middleware\HandleCors;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['XSRF-TOKEN', '__Host-XSRF-TOKEN']);

        $middleware->api(prepend: [
            SecurityHeaders::class,
            AssignRequestId::class,
            HandleCors::class,
            EncryptCookies::class,
            VerifyCookieCsrfToken::class,
            AuthenticateFromCookie::class,
        ]);

        $middleware->alias([
            'active'        => EnsureUserIsActive::class,
            'users.view'    => EnsureUserCanViewUsers::class,
            'impersonation' => RestrictImpersonatedSession::class,
        ]);

        $middleware->redirectGuestsTo(fn () => null);
    })
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('audit:prune')->daily();
        $schedule->command('impersonations:prune')->daily();
        $schedule->command('accounts:anonymize-trashed')->daily();

        // Bill/tuition generation runs at the start of each month so members have
        // the whole month's notice before the configured due day.
        $schedule->command('bills:generate-recurrent')->monthlyOn(1, '02:00');
        $schedule->command('tuitions:generate')->monthlyOn(1, '02:00');

        // Billing reminders run every morning: 5 days before and 1 day after due.
        $schedule->command('notifications:send-billing-reminders')->dailyAt('08:00');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(fn (Request $request) => true);

        $exceptions->render(
            fn (Throwable $e): JsonResponse => ApiExceptionRenderer::render($e, (bool) config('app.debug'))
        );
    })->create();
