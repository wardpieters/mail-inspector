<?php

namespace Tests\Unit;

use App\Mail\Dns\DnsClient;
use App\Mail\Dns\MxDiscovery;
use App\Mail\Enums\Outcome;
use App\Mail\Value\ScanBudget;
use App\Mail\Value\ScanInput;
use Tests\Support\FakeDnsClient;
use Tests\TestCase;

class RoutingFailureTest extends TestCase
{
    public function test_resolver_errors_cannot_prove_broken_mx_or_missing_addresses(): void
    {
        foreach (['ERROR', 'SERVFAIL'] as $status) {
            $dns = new FakeDnsClient(['fixture.test MX' => ['status' => $status]]);
            $this->app->instance(DnsClient::class, $dns);
            $routing = app(MxDiscovery::class)->discover(new ScanInput('fixture.test'), new ScanBudget(30));
            $this->assertSame(Outcome::Inconclusive, collect($routing->findings)->firstWhere('key', 'mx')->outcome);
            $dns->add('fixture.test', 'MX', [['preference' => 0, 'exchange' => 'mx.test']]);
            $dns->responses['mx.test A'] = ['status' => $status];
            $dns->responses['mx.test AAAA'] = ['status' => $status];
            $routing = app(MxDiscovery::class)->discover(new ScanInput('fixture.test'), new ScanBudget(30));
            $this->assertSame(Outcome::Inconclusive, collect($routing->findings)->firstWhere('key', 'mx_no_address')->outcome);
        }
    }

    public function test_definitive_negative_addresses_remain_configuration_failure(): void
    {
        $dns = new FakeDnsClient;
        $dns->add('fixture.test', 'MX', [['preference' => 0, 'exchange' => 'mx.test']]);
        $dns->responses['mx.test A'] = ['status' => 'NXDOMAIN'];
        $dns->responses['mx.test AAAA'] = ['status' => 'NXDOMAIN'];
        $this->app->instance(DnsClient::class, $dns);
        $routing = app(MxDiscovery::class)->discover(new ScanInput('fixture.test'), new ScanBudget(30));
        $this->assertSame(Outcome::Fail, collect($routing->findings)->firstWhere('key', 'mx_no_address')->outcome);
    }
}
