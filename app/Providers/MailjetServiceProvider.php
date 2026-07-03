<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\Mailer\Bridge\Mailjet\Transport\MailjetTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;

class MailjetServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Mail::extend('mailjet', function (array $config) {
            $scheme = ($config['mode'] ?? 'api') === 'smtp'
                ? 'mailjet+smtp'
                : 'mailjet+api';

            $endpoint = $config['endpoint'] ?? 'default';
            $key = $config['key'] ?? config('services.mailjet.key');
            $secret = $config['secret'] ?? config('services.mailjet.secret');

            $dsn = new Dsn(
                $scheme,
                is_string($endpoint) ? $endpoint : 'default',
                is_string($key) ? $key : null,
                is_string($secret) ? $secret : null,
            );

            return (new MailjetTransportFactory)->create($dsn);
        });
    }
}
