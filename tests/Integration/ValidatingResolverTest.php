<?php

namespace Tests\Integration;

use App\Mail\Dns\NetDnsClient;
use App\Mail\Value\ScanBudget;
use Tests\TestCase;

class ValidatingResolverTest extends TestCase
{
    public function test_real_resolver_validates_signed_and_unsigned_answers_and_rejects_bogus_data(): void
    {
        if (! env('MAIL_TEST_DNS')) {
            $this->markTestSkipped('Requires cryptographically signed BIND/Unbound fixtures.');
        }
        config(['mail_scan.dns_server' => env('MAIL_TEST_DNS_HOST', 'dns-test'), 'mail_scan.dns_port' => 1053, 'mail_scan.dns_trusted' => true]);
        $client = new NetDnsClient;
        $budget = new ScanBudget(30);
        $secure = $client->query('good.fixture.test', 'MX', $budget);
        $this->assertSame('secure', $secure->validation);
        $this->assertSame('mx.good.fixture.test', $secure->ofType()[0]['data']['exchange']);
        $unsigned = $client->query('unsigned.fixture.test', 'MX', $budget);
        $this->assertSame('insecure', $unsigned->validation);
        $bogus = $client->query('mx.bogus.fixture.test', 'A', $budget);
        $this->assertSame('SERVFAIL', $bogus->status);
        $this->assertSame('bogus', $bogus->validation);
        $disabled = $client->query('mx.bogus.fixture.test', 'A', $budget, true);
        $this->assertSame('NOERROR', $disabled->status);
        $this->assertSame('indeterminate', $disabled->validation);
        $txt = $client->query('good.fixture.test', 'TXT', $budget);
        $this->assertSame('v=spf1 -all', $txt->texts()[0]);
    }
}
