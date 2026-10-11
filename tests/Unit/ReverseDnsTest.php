<?php

namespace Tests\Unit;

use App\Mail\Dns\DnsClient;
use App\Mail\Dns\ReverseDns;
use App\Mail\Enums\Outcome;
use App\Mail\Value\EndpointTarget;
use App\Mail\Value\ScanBudget;
use Tests\Support\FakeDnsClient;
use Tests\TestCase;

class ReverseDnsTest extends TestCase
{
    public function test_compares_ipv6_in_binary_form(): void
    {
        $dns = new FakeDnsClient;
        $ip = '2606:4700:4700::1111';
        $reverse = implode('.', array_reverse(str_split(bin2hex(inet_pton($ip))))).'.ip6.arpa';
        $dns->add($reverse, 'PTR', [['target' => 'mx.test']]);
        $dns->add('mx.test', 'AAAA', [['address' => '2606:4700:4700:0:0:0:0:1111']]);
        $this->app->instance(DnsClient::class, $dns);
        $this->assertSame(Outcome::Pass, app(ReverseDns::class)->analyze(new EndpointTarget('mx.test', 0, $ip, 6), new ScanBudget(300))->outcome);
    }
}
