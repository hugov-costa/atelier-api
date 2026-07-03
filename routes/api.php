<?php

declare(strict_types=1);

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AvatarController;
use App\Http\Controllers\BillController;
use App\Http\Controllers\ClayController;
use App\Http\Controllers\ClaySupplierController;
use App\Http\Controllers\CommissionOrderController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\EmailVerificationController;
use App\Http\Controllers\EnrollmentController;
use App\Http\Controllers\FiringCycleController;
use App\Http\Controllers\GlazeController;
use App\Http\Controllers\GlazeSupplierController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\ImpersonationController;
use App\Http\Controllers\MaterialPurchaseController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\PieceCategoryController;
use App\Http\Controllers\PieceChargeController;
use App\Http\Controllers\PieceController;
use App\Http\Controllers\RecurrentClassController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SetPasswordController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\SingleClassController;
use App\Http\Controllers\StudentStatementController;
use App\Http\Controllers\TuitionFeeController;
use App\Http\Controllers\TwoFactorController;
use App\Http\Controllers\UserAuditController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('/health', [HealthController::class, 'show'])
        ->middleware('throttle:60,1')
        ->name('health');

    /*
     * Guest endpoints (rate-limited by the stricter auth throttle).
     */
    Route::middleware('throttle:auth')->group(function () {
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/users/master', [UserController::class, 'storeMaster'])->name('users.master');

        Route::post('/forgot-password', [PasswordResetController::class, 'sendResetLink'])
            ->name('password.email');
        Route::post('/reset-password', [PasswordResetController::class, 'reset'])
            ->name('password.reset');

        Route::post('/set-password/request', [SetPasswordController::class, 'request'])
            ->name('set-password.request');
        Route::post('/set-password/validate-token', [SetPasswordController::class, 'validateToken'])
            ->name('set-password.validate');
        Route::post('/set-password/confirm', [SetPasswordController::class, 'confirm'])
            ->name('set-password.confirm');
    });

    Route::middleware('throttle:api')->group(function () {
        Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
            ->middleware(['auth:sanctum', 'signed'])
            ->name('verification.verify');

        /*
         * The authenticated user's own account: profile, credentials, two-factor and
         * in-app notifications.
         */
        Route::middleware(['auth:sanctum', 'impersonation', 'active'])->group(function () {
            Route::get('/user', [AuthController::class, 'user'])->name('user.show');
            Route::get('/user/export', [AccountController::class, 'export'])->name('account.export');
            Route::put('/user/password', [AuthController::class, 'updatePassword'])->name('user.password');
            Route::delete('/user', [AccountController::class, 'destroy'])->name('account.destroy');
            Route::post('/logout', [AuthController::class, 'logout'])->name('auth.logout');

            Route::post('/email/verification-notification', [EmailVerificationController::class, 'resend'])
                ->name('verification.send');

            Route::post('/two-factor/enable', [TwoFactorController::class, 'enable'])
                ->name('two-factor.enable');
            Route::post('/two-factor/confirm', [TwoFactorController::class, 'confirm'])
                ->name('two-factor.confirm');
            Route::post('/two-factor/recovery-codes', [TwoFactorController::class, 'regenerateRecoveryCodes'])
                ->name('two-factor.recovery');
            Route::delete('/two-factor', [TwoFactorController::class, 'disable'])
                ->name('two-factor.disable');

            Route::delete('/impersonate', [ImpersonationController::class, 'stop'])
                ->name('impersonation.stop');

            Route::get('/notifications', [NotificationController::class, 'index'])
                ->name('notifications.index');
            Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead'])
                ->name('notifications.read-all');
            Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead'])
                ->name('notifications.read');
        });

        /*
         * Audit trail, readable by staff.
         */
        Route::middleware(['auth:sanctum', 'users.view'])->group(function () {
            Route::get('/users/audits', [UserAuditController::class, 'index'])
                ->name('users.audits.index');
            Route::get('/users/{user}/audits', [UserAuditController::class, 'byUser'])
                ->name('users.audits.byUser');
        });

        /*
         * User management, impersonation and per-user assets.
         */
        Route::middleware(['auth:sanctum', 'impersonation', 'active'])->group(function () {
            Route::get('/users', [UserController::class, 'index'])->name('users.index');
            Route::post('/users', [UserController::class, 'store'])->name('users.store');
            Route::get('/users/{user}', [UserController::class, 'show'])->name('users.show');
            Route::patch('/users/{user}', [UserController::class, 'update'])->name('users.update');
            Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
            Route::post('/users/{user}/erase', [UserController::class, 'erase'])->name('users.erase');
            Route::post('/users/{user}/restore', [UserController::class, 'restore'])
                ->withTrashed()
                ->name('users.restore');

            Route::get('/students/{student}/statement', [StudentStatementController::class, 'show'])
                ->name('students.statement');

            Route::post('/users/{user}/impersonate', [ImpersonationController::class, 'start'])
                ->name('impersonation.start');

            Route::post('/users/{user}/avatar', [AvatarController::class, 'store'])
                ->name('users.avatar.store');
            Route::delete('/users/{user}/avatar', [AvatarController::class, 'destroy'])
                ->name('users.avatar.destroy');
        });

        Route::middleware(['auth:sanctum', 'impersonation', 'active'])->group(function () {
            $softDeletable = function (string $name, string $controller, string $parameter): void {
                Route::post("/{$name}/{{$parameter}}/restore", [$controller, 'restore'])
                    ->withTrashed()
                    ->name("{$name}.restore");
                Route::apiResource($name, $controller);
            };

            // Configuration
            Route::get('/settings', [SettingController::class, 'show'])->name('settings.show');
            Route::put('/settings', [SettingController::class, 'update'])->name('settings.update');
            Route::post('/settings/logo', [SettingController::class, 'storeLogo'])
                ->name('settings.logo.store');
            Route::delete('/settings/logo', [SettingController::class, 'destroyLogo'])
                ->name('settings.logo.destroy');

            // Finance
            $softDeletable('bills', BillController::class, 'bill');
            $softDeletable('tuition-fees', TuitionFeeController::class, 'tuition_fee');
            Route::apiResource('piece-charges', PieceChargeController::class)
                ->only(['index', 'show', 'update']);
            Route::get('/reports/monthly', [ReportController::class, 'monthly'])
                ->name('reports.monthly');

            // Materials & catalog
            $softDeletable('clay-suppliers', ClaySupplierController::class, 'clay_supplier');
            $softDeletable('clays', ClayController::class, 'clay');
            $softDeletable('glaze-suppliers', GlazeSupplierController::class, 'glaze_supplier');
            $softDeletable('glazes', GlazeController::class, 'glaze');
            $softDeletable('material-purchases', MaterialPurchaseController::class, 'material_purchase');
            $softDeletable('piece-categories', PieceCategoryController::class, 'piece_category');
            $softDeletable('firing-cycles', FiringCycleController::class, 'firing_cycle');

            // Production & sales
            $softDeletable('pieces', PieceController::class, 'piece');
            $softDeletable('commission-orders', CommissionOrderController::class, 'commission_order');
            $softDeletable('customers', CustomerController::class, 'customer');

            // Classes & enrollment
            $softDeletable('single-classes', SingleClassController::class, 'single_class');
            $softDeletable('recurrent-classes', RecurrentClassController::class, 'recurrent_class');
            $softDeletable('enrollments', EnrollmentController::class, 'enrollment');
        });
    });
});
