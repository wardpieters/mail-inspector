<?php

namespace App\Providers;

use App\Mail\Dns\DnsClient;
use App\Mail\Dns\NetDnsClient;
use App\Mail\Smtp\SmtpSessionFactory;
use App\Mail\Smtp\StreamSmtpSessionFactory;
use Illuminate\Support\ServiceProvider;

class MailScannerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(DnsClient::class, NetDnsClient::class);
        $this->app->bind(SmtpSessionFactory::class, StreamSmtpSessionFactory::class);
    }
}
