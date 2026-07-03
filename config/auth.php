<?php

declare(strict_types=1);

use App\Models\User;

return [

    /*
    |--------------------------------------------------------------------------
    | API Token Cookie
    |--------------------------------------------------------------------------
    |
    | Settings for the httpOnly cookie that carries the Sanctum token for
    | browser clients. Use SameSite "none" (with secure cookies over HTTPS)
    | when the frontend lives on a different site than the API.
    |
    */

    'token_cookie' => [
        'name'      => env('AUTH_COOKIE_NAME', 'access_token'),
        'lifetime'  => (int) env('AUTH_COOKIE_LIFETIME', 60 * 24 * 14),
        'secure'    => env('APP_ENV') === 'production' ? true : (bool) env('AUTH_COOKIE_SECURE', false),
        'same_site' => env('AUTH_COOKIE_SAME_SITE', 'lax'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Impersonation
    |--------------------------------------------------------------------------
    |
    | A master may temporarily act as another user for support. The session is
    | time-boxed and carried by a dedicated httpOnly cookie, separate from the
    | master's own token cookie so the master is never logged out.
    |
    */

    'impersonation' => [
        'cookie'         => env('IMPERSONATION_COOKIE_NAME', 'impersonate_token'),
        'token_name'     => 'impersonation',
        'ttl_minutes'    => (int) env('IMPERSONATION_TTL_MINUTES', 30),
        'retention_days' => (int) env('IMPERSONATION_RETENTION_DAYS', 180),
    ],

    /*
    |--------------------------------------------------------------------------
    | Account
    |--------------------------------------------------------------------------
    |
    | Soft-deleted accounts are kept briefly so an accidental deletion can be
    | undone. Past this window the personal data is anonymised by the
    | accounts:anonymize-trashed command, so removed accounts do not retain
    | identifying data indefinitely (data-minimisation, LGPD Art. 15/16).
    |
    */

    'account' => [
        'anonymize_trashed_days' => (int) env('ACCOUNT_ANONYMIZE_TRASHED_DAYS', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication Defaults
    |--------------------------------------------------------------------------
    |
    | This option defines the default authentication "guard" and password
    | reset "broker" for your application. You may change these values
    | as required, but they're a perfect start for most applications.
    |
    */

    'defaults' => [
        'guard'     => env('AUTH_GUARD', 'web'),
        'passwords' => env('AUTH_PASSWORD_BROKER', 'users'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication Guards
    |--------------------------------------------------------------------------
    |
    | Next, you may define every authentication guard for your application.
    | Of course, a great default configuration has been defined for you
    | which utilizes session storage plus the Eloquent user provider.
    |
    | All authentication guards have a user provider, which defines how the
    | users are actually retrieved out of your database or other storage
    | system used by the application. Typically, Eloquent is utilized.
    |
    | Supported: "session"
    |
    */

    'guards' => [
        'web' => [
            'driver'   => 'session',
            'provider' => 'users',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | User Providers
    |--------------------------------------------------------------------------
    |
    | All authentication guards have a user provider, which defines how the
    | users are actually retrieved out of your database or other storage
    | system used by the application. Typically, Eloquent is utilized.
    |
    | If you have multiple user tables or models you may configure multiple
    | providers to represent the model / table. These providers may then
    | be assigned to any extra authentication guards you have defined.
    |
    | Supported: "database", "eloquent"
    |
    */

    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model'  => env('AUTH_MODEL', User::class),
        ],

        // 'users' => [
        //     'driver' => 'database',
        //     'table' => 'users',
        // ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Resetting Passwords
    |--------------------------------------------------------------------------
    |
    | These configuration options specify the behavior of Laravel's password
    | reset functionality, including the table utilized for token storage
    | and the user provider that is invoked to actually retrieve users.
    |
    | The expiry time is the number of minutes that each reset token will be
    | considered valid. This security feature keeps tokens short-lived so
    | they have less time to be guessed. You may change this as needed.
    |
    | The throttle setting is the number of seconds a user must wait before
    | generating more password reset tokens. This prevents the user from
    | quickly generating a very large amount of password reset tokens.
    |
    */

    'passwords' => [
        'users' => [
            'provider' => 'users',
            'table'    => env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens'),
            'expire'   => 60,
            'throttle' => 60,
        ],

        // Dedicated broker for onboarding accounts that have no password yet.
        // Tokens are hashed at rest and expire after the configured window, so
        // the link a member receives to choose their first password is secure
        // and single-use, separate from the password-reset broker above.
        'set_password' => [
            'provider' => 'users',
            'table'    => 'set_password_tokens',
            'expire'   => (int) env('SET_PASSWORD_TOKEN_EXPIRATION', 1440),
            'throttle' => 60,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Password Confirmation Timeout
    |--------------------------------------------------------------------------
    |
    | Here you may define the number of seconds before a password confirmation
    | window expires and users are asked to re-enter their password via the
    | confirmation screen. By default, the timeout lasts for three hours.
    |
    */

    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),

];
