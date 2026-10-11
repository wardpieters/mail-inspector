<?php

namespace Tests\Unit;

use App\Mail\Checks\CaaCheck;
use App\Mail\Dns\DnsClient;
use App\Mail\Enums\Outcome;
use App\Mail\Value\ScanBudget;
use Tests\Support\FakeDnsClient;
use Tests\TestCase;

class CaaSyntaxTest extends TestCase
{
    public function test_malformed_recognized_properties_cannot_pass(): void
    {
        $dns = new FakeDnsClient;
        $this->app->instance(DnsClient::class, $dns);
        foreach ([['issue', 'not a valid issuer!'], ['issuewild', 'bad..test'], ['iodef', 'not-a-url']] as [$tag,$value]) {
            $dns->add('fixture.test', 'CAA', [['flags' => 0, 'tag' => $tag, 'value' => $value]]);
            $this->assertSame(Outcome::Fail, app(CaaCheck::class)->analyze('fixture.test', new ScanBudget(30))[0]->outcome);
        }
    }

    public function test_valid_issuers_deny_all_and_reporting_uris_remain_valid(): void
    {
        $dns = new FakeDnsClient;
        $this->app->instance(DnsClient::class, $dns);
        foreach ([['issue', 'letsencrypt.org; accounturi=https://acme.test/acct/1'], ['issue', ';'], ['issuewild', ''], ['iodef', 'mailto:security@fixture.test'], ['iodef', 'https://fixture.test/report']] as [$tag,$value]) {
            $dns->add('fixture.test', 'CAA', [['flags' => 0, 'tag' => $tag, 'value' => $value]]);
            $this->assertSame(Outcome::Pass, app(CaaCheck::class)->analyze('fixture.test', new ScanBudget(30))[0]->outcome, $tag.' '.$value);
        }
    }
}
