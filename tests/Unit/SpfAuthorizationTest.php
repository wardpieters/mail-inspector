<?php

namespace Tests\Unit;

use App\Mail\Checks\SpfCheck;
use App\Mail\Dns\DnsClient;
use App\Mail\Enums\Outcome;
use App\Mail\Value\ScanBudget;
use App\Mail\Value\ScanInput;
use Tests\Support\FakeDnsClient;
use Tests\TestCase;

class SpfAuthorizationTest extends TestCase
{
    public function test_universal_address_ranges_and_pass_all_dependencies_cannot_pass(): void
    {
        $dns = new FakeDnsClient;
        $dns->add('open.test', 'TXT', [['text' => 'v=spf1 +all']]);
        $this->app->instance(DnsClient::class, $dns);
        foreach (['ip4:0.0.0.0/0 -all', 'ip6:::/0 -all', 'include:open.test -all', 'redirect=open.test'] as $terms) {
            $dns->add('fixture.test', 'TXT', [['text' => 'v=spf1 '.$terms]]);
            $finding = app(SpfCheck::class)->analyze(new ScanInput('fixture.test'), new ScanBudget(30))[0];
            $this->assertSame(Outcome::Fail, $finding->outcome, $terms);
        }
    }

    public function test_qualified_exclusions_and_unreachable_includes_do_not_authorize_everyone(): void
    {
        $dns = new FakeDnsClient;
        $dns->add('open.test', 'TXT', [['text' => 'v=spf1 +all']]);
        $this->app->instance(DnsClient::class, $dns);
        foreach (['-include:open.test -all', '-all include:open.test', '-ip4:0.0.0.0/0 -all'] as $terms) {
            $dns->add('fixture.test', 'TXT', [['text' => 'v=spf1 '.$terms]]);
            $this->assertSame(Outcome::Pass, app(SpfCheck::class)->analyze(new ScanInput('fixture.test'), new ScanBudget(30))[0]->outcome, $terms);
        }
    }
}
