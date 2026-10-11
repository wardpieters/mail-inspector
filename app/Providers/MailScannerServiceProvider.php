<?php

namespace App\Providers;

use App\Mail\Dns\DnsClient;
use App\Mail\Dns\NetDnsClient;
use Illuminate\Support\ServiceProvider;

class MailScannerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(DnsClient::class, NetDnsClient::class);
    }
}
