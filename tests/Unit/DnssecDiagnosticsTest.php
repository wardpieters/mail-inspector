<?php

namespace Tests\Unit;

use App\Mail\Dns\DnssecDiagnostics;
use App\Mail\Enums\Outcome;
use App\Mail\Value\DnsResult;
use App\Mail\Value\ScanBudget;
use Tests\TestCase;

class DnssecDiagnosticsTest extends TestCase
{
    public function test_servfail_is_not_automatically_bogus_and_trusted_validation_passes(): void
    {
        $check = app(DnssecDiagnostics::class);
        $budget = new ScanBudget(300);
        $this->assertSame(Outcome::Inconclusive, $check->analyze(new DnsResult('fixture.test', 'MX', 'SERVFAIL'), $budget)->outcome);
        $this->assertSame(Outcome::Pass, $check->analyze(new DnsResult('fixture.test', 'MX', 'NOERROR', [], 'secure'), $budget)->outcome);
    }
}
