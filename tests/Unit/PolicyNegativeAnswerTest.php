<?php

namespace Tests\Unit;

use App\Mail\Checks\DaneCheck;
use App\Mail\Checks\TransportPolicies;
use App\Mail\Dns\DnsClient;
use App\Mail\Dns\ReverseDns;
use App\Mail\Enums\Outcome;
use App\Mail\Value\DnsResult;
use App\Mail\Value\EndpointTarget;
use App\Mail\Value\ProbeResult;
use App\Mail\Value\RoutingSnapshot;
use App\Mail\Value\ScanBudget;
use App\Mail\Value\ScanInput;
use Tests\Support\FakeDnsClient;
use Tests\TestCase;

class PolicyNegativeAnswerTest extends TestCase
{
    public function test_authenticated_tlsa_nxdomain_is_policy_absence(): void
    {
        $answer = new DnsResult('_25._tcp.mx.test', 'TLSA', 'NXDOMAIN', [], 'secure');
        $finding = app(DaneCheck::class)->evaluate(new EndpointTarget('mx.test', 0, '8.8.8.8', 4, true), new ProbeResult([], true), $answer, new ScanBudget(30));
        $this->assertSame(Outcome::Info, $finding->outcome);
        $this->assertSame('secure', $finding->evidence['tlsa']['validation']);
    }

    public function test_optional_tlsrpt_nxdomain_is_not_incomplete(): void
    {
        $dns = new FakeDnsClient(['_smtp._tls.fixture.test TXT' => ['status' => 'NXDOMAIN', 'validation' => 'secure']]);
        $this->app->instance(DnsClient::class, $dns);
        $snapshot = app(TransportPolicies::class)->discover(new ScanInput('fixture.test'), new RoutingSnapshot([], []), new ScanBudget(30));
        $finding = collect($snapshot->findings)->firstWhere('key', 'tlsrpt');
        $this->assertSame(Outcome::Info, $finding->outcome);
    }

    public function test_ptr_nxdomain_is_missing_name_and_not_resolver_failure(): void
    {
        $dns = new FakeDnsClient(['8.8.8.8.in-addr.arpa PTR' => ['status' => 'NXDOMAIN', 'validation' => 'insecure']]);
        $this->app->instance(DnsClient::class, $dns);
        $this->assertSame(Outcome::Warning, app(ReverseDns::class)->analyze(new EndpointTarget('mx.test', 0, '8.8.8.8', 4), new ScanBudget(30))->outcome);
    }
}
