<?php

namespace App\Console\Commands;

use App\Mail\Dns\DnsClient;
use App\Mail\Network\TargetGuard;
use App\Mail\Smtp\SmtpSessionFactory;
use App\Mail\Value\EndpointTarget;
use App\Mail\Value\ScanBudget;
use Illuminate\Console\Command;

class DiagnoseScanner extends Command
{
    protected $signature = 'scanner:diagnose {--connect= : Operator-selected public IP to test on port 25} {--mx= : MX hostname for the selected address}';

    protected $description = 'Inspect scanner DNS, queue, crypto and optional SMTP egress';

    public function handle(): int
    {
        $this->table(['Capability', 'Observation'], [['PHP', PHP_VERSION], ['OpenSSL', OPENSSL_VERSION_TEXT], ['Queue', config('queue.default')], ['DNS resolver', config('mail_scan.dns_server')], ['DNS trusted', config('mail_scan.dns_trusted') ? 'yes' : 'no'], ['HELO', config('mail_scan.helo')], ['Sender', config('mail_scan.sender') ? 'configured' : 'null sender'], ['Relay identities', config('mail_scan.relay_sender') && config('mail_scan.relay_recipient') ? 'configured' : 'not configured'], ['IPv6', defined('AF_INET6') ? 'socket family available; egress unverified' : 'socket family unavailable']]);
        $dns = app(DnsClient::class)->query('.', 'DNSKEY', new ScanBudget(5));
        $this->line('Root DNS validation: '.$dns->status.' / '.$dns->validation);
        if ($ip = $this->option('connect')) {
            try {
                app(TargetGuard::class)->assertPublic($ip);
                $mx = $this->option('mx') ?: config('mail_scan.helo');
                $session = app(SmtpSessionFactory::class)->open(new EndpointTarget($mx, 0, $ip, str_contains($ip, ':') ? 6 : 4), new ScanBudget(5));
                $session->close();
                $this->info('TCP port 25 connected to the requested public address.');
            } catch (\Throwable $e) {
                $this->error($e->getMessage());

                return self::FAILURE;
            }
        }

        return $dns->ok() ? self::SUCCESS : self::FAILURE;
    }
}
