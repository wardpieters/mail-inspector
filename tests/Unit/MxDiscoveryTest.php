<?php

namespace Tests\Unit;

use App\Mail\Dns\DnsClient;
use App\Mail\Dns\MxDiscovery;
use App\Mail\Value\ScanBudget;
use App\Mail\Value\ScanInput;
use Tests\Support\FakeDnsClient;
use Tests\TestCase;

class MxDiscoveryTest extends TestCase
{
    public function test_discovers_every_address_on_every_mx(): void
    {
        $dns = new FakeDnsClient;
        $dns->add('fixture.test', 'MX', [['preference' => 10, 'exchange' => 'mx1.test'], ['preference' => 20, 'exchange' => 'mx2.test']]);
        foreach (['mx1.test', 'mx2.test'] as $mx) {
            $dns->add($mx, 'A', [['address' => '8.8.8.8'], ['address' => '8.8.4.4']]);
            $dns->add($mx, 'AAAA', [['address' => '2606:4700:4700::1111'], ['address' => '2606:4700:4700::1001']]);
        }
        $this->app->instance(DnsClient::class, $dns);
        $snapshot = app(MxDiscovery::class)->discover(new ScanInput('fixture.test'), new ScanBudget(300));
        $this->assertCount(8, $snapshot->targets);
        $this->assertFalse($snapshot->nullMx);
        $this->assertTrue($snapshot->targets[0]->discoverySecure);
    }

    public function test_null_mx_does_not_probe_and_nxdomain_does_not_fall_back(): void
    {
        $dns = new FakeDnsClient;
        $dns->add('fixture.test', 'MX', [['preference' => 0, 'exchange' => '.']]);
        $this->app->instance(DnsClient::class, $dns);
        $snapshot = app(MxDiscovery::class)->discover(new ScanInput('fixture.test'), new ScanBudget(300));
        $this->assertTrue($snapshot->nullMx);
        $this->assertCount(0, $snapshot->targets);
        $dns->responses['fixture.test MX'] = ['status' => 'NXDOMAIN'];
        $snapshot = app(MxDiscovery::class)->discover(new ScanInput('fixture.test'), new ScanBudget(300));
        $this->assertCount(0, $snapshot->targets);
        $this->assertCount(2, $dns->queries());
    }

    public function test_implicit_mx_and_private_target_rejection(): void
    {
        $dns = new FakeDnsClient;
        $dns->add('fixture.test', 'A', [['address' => '8.8.8.8'], ['address' => '127.0.0.1']]);
        $this->app->instance(DnsClient::class, $dns);
        $snapshot = app(MxDiscovery::class)->discover(new ScanInput('fixture.test'), new ScanBudget(300));
        $this->assertCount(1, $snapshot->targets);
        $this->assertSame('fixture.test', $snapshot->targets[0]->mx);
        $this->assertContains('mx_target_blocked', array_column(array_map(fn ($f) => $f->jsonSerialize(), $snapshot->findings), 'key'));
    }
}
