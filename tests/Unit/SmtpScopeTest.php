<?php

namespace Tests\Unit;

use App\Mail\Checks\SmtpProbe;
use App\Mail\Enums\Outcome;
use App\Mail\Smtp\SmtpSession;
use App\Mail\Smtp\SmtpSessionFactory;
use App\Mail\Value\EndpointTarget;
use App\Mail\Value\ScanBudget;
use Tests\Support\FakeSmtpSession;
use Tests\Support\FakeSmtpSessionFactory;
use Tests\TestCase;

class SmtpScopeTest extends TestCase
{
    public function test_local_network_unreachable_is_a_scanner_limitation(): void
    {
        $factory = new class implements SmtpSessionFactory
        {
            public function open(EndpointTarget $target, ScanBudget $budget): SmtpSession
            {
                throw new \RuntimeException('Network is unreachable', 101);
            }
        };
        $this->app->instance(SmtpSessionFactory::class, $factory);
        $finding = app(SmtpProbe::class)->probe(new EndpointTarget('mx.test', 0, '2606:4700:4700::1111', 6), new ScanBudget(30))->findings[0];
        $this->assertSame(Outcome::Inconclusive, $finding->outcome);
    }

    public function test_local_envelope_domains_are_not_tested_as_external_relay(): void
    {
        foreach ([['s@fixture.test', 'r@external.test'], ['s@scanner.test', 'r@FIXTURE.TEST'], ['s@scanner.test', 'r@sub.fixture.test']] as [$sender,$recipient]) {
            config(['mail_scan.relay_sender' => $sender, 'mail_scan.relay_recipient' => $recipient]);
            $session = new FakeSmtpSession(['greeting' => [220, ['mx.test']]]);
            $this->app->instance(SmtpSessionFactory::class, new FakeSmtpSessionFactory($session));
            $probe = app(SmtpProbe::class)->probe(new EndpointTarget('mx.test', 0, '8.8.8.8', 4), new ScanBudget(30), 'fixture.test');
            $finding = collect($probe->findings)->firstWhere('key', 'open_relay');
            $this->assertSame(Outcome::Skipped, $finding->outcome);
            $this->assertNotContains('RCPT', array_map(fn ($command) => explode(' ', $command)[0], $session->commands));
        }
    }
}
