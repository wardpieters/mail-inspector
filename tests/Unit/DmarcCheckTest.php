<?php

namespace Tests\Unit;

use App\Mail\Checks\DmarcCheck;
use App\Mail\Dns\DmarcDiscovery;
use App\Mail\Dns\DnsClient;
use App\Mail\Enums\Outcome;
use App\Mail\Value\ScanBudget;
use App\Mail\Value\ScanInput;
use Tests\Support\FakeDnsClient;
use Tests\TestCase;

class DmarcCheckTest extends TestCase
{
    public function test_inherits_organizational_policy_instead_of_closest_parent(): void
    {
        $dns = new FakeDnsClient;
        $dns->add('_dmarc.mail.example.test', 'TXT', [['text' => 'v=DMARC1; p=none']]);
        $dns->add('_dmarc.example.test', 'TXT', [['text' => 'v=DMARC1; p=reject; sp=quarantine; psd=n']]);
        $this->app->instance(DnsClient::class, $dns);
        $result = app(DmarcDiscovery::class)->discover('a.mail.example.test', new ScanBudget(300));
        $this->assertSame('example.test', $result['source']);
        $this->assertSame('quarantine', $result['effective_policy']);
    }

    public function test_direct_policy_and_external_reporting_authorization(): void
    {
        $dns = new FakeDnsClient;
        $dns->add('_dmarc.fixture.test', 'TXT', [['text' => 'v=DMARC1; p=reject; rua=mailto:reports@external.test']]);
        $dns->add('fixture.test._report._dmarc.external.test', 'TXT', [['text' => 'v=DMARC1']]);
        $this->app->instance(DnsClient::class, $dns);
        $findings = app(DmarcCheck::class)->analyze(new ScanInput('fixture.test'), new ScanBudget(300));
        $this->assertSame(Outcome::Pass, $findings[0]->outcome);
        $this->assertSame(Outcome::Pass, $findings[1]->outcome);
    }

    public function test_tree_walk_is_bounded_to_eight_policy_queries(): void
    {
        $dns = new FakeDnsClient;
        $this->app->instance(DnsClient::class, $dns);
        app(DmarcDiscovery::class)->discover('a.b.c.d.e.f.g.h.i.j.example.test', new ScanBudget(300));
        $this->assertCount(8, array_filter($dns->queries(), fn ($q) => str_starts_with($q[0],'_dmarc.')));
    }
}
