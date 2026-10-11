<?php

namespace Tests\Unit;

use App\Mail\Checks\SpfCheck;
use App\Mail\Dns\DnsClient;
use App\Mail\Dns\SpfParser;
use App\Mail\Enums\Outcome;
use App\Mail\Value\ScanBudget;
use App\Mail\Value\ScanInput;
use Tests\Support\FakeDnsClient;
use Tests\TestCase;

class SpfCheckTest extends TestCase
{
    public function test_rejects_invalid_cidrs_and_preserves_unknown_modifiers(): void
    {
        $parser = app(SpfParser::class);
        foreach (['v=spf1 ip4:999.1.1.1 -all', 'v=spf1 ip6:2001:db8::/129 -all', 'v=spf1 a/33 -all', 'v=spf1 unknown -all'] as $record) {
            $this->assertNotEmpty($parser->parse($record)['diagnostics']);
        }
        $this->assertEmpty($parser->parse('v=spf1 ip4:8.8.8.0/24 ip6:2606:4700::/32 foo=bar -all')['diagnostics']);
    }

    public function test_multiple_spf_records_and_include_cycles_fail(): void
    {
        $dns = new FakeDnsClient;
        $dns->add('fixture.test', 'TXT', [['text' => 'v=spf1 -all'], ['text' => 'v=spf1 +all']]);
        $this->app->instance(DnsClient::class, $dns);
        $this->assertSame(Outcome::Fail, app(SpfCheck::class)->analyze(new ScanInput('fixture.test'), new ScanBudget(300))[0]->outcome);
        $dns->add('fixture.test', 'TXT', [['text' => 'v=spf1 include:other.test -all']]);
        $dns->add('other.test', 'TXT', [['text' => 'v=spf1 redirect=fixture.test']]);
        $this->assertSame(Outcome::Fail, app(SpfCheck::class)->analyze(new ScanInput('fixture.test'), new ScanBudget(300))[0]->outcome);
    }

    public function test_softfail_and_allow_all_do_not_get_strict_policy_pass(): void
    {
        $dns = new FakeDnsClient;
        $this->app->instance(DnsClient::class, $dns);
        foreach (['~all' => Outcome::Warning, '+all' => Outcome::Fail, '-all' => Outcome::Pass] as $term => $outcome) {
            $dns->add('fixture.test', 'TXT', [['text' => 'v=spf1 '.$term]]);
            $this->assertSame($outcome, app(SpfCheck::class)->analyze(new ScanInput('fixture.test'), new ScanBudget(300))[0]->outcome);
        }
    }
}
