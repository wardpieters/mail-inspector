<?php

namespace Tests\Unit;

use App\Mail\Checks\CaaCheck;
use App\Mail\Dns\DnsClient;
use App\Mail\Enums\Outcome;
use App\Mail\Value\ScanBudget;
use Tests\Support\FakeDnsClient;
use Tests\TestCase;

class CaaCheckTest extends TestCase
{
    public function test_finds_parent_policy_and_warns_for_critical_unknown_property(): void
    {
        $dns = new FakeDnsClient;
        $dns->add('example.test', 'CAA', [['flags' => 128, 'tag' => 'unknown', 'value' => 'x']]);
        $this->app->instance(DnsClient::class, $dns);
        $finding = app(CaaCheck::class)->analyze('mail.example.test', new ScanBudget(300))[0];
        $this->assertSame(Outcome::Warning, $finding->outcome);
        $this->assertSame('example.test', $finding->evidence['source']);
    }

    public function test_absent_caa_is_informational(): void
    {
        $this->app->instance(DnsClient::class, new FakeDnsClient);
        $this->assertSame(Outcome::Info, app(CaaCheck::class)->analyze('fixture.test', new ScanBudget(300))[0]->outcome);
    }
}
