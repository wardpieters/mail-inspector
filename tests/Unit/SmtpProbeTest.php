<?php

namespace Tests\Unit;

use App\Mail\Checks\SmtpProbe;
use App\Mail\Enums\Outcome;
use App\Mail\Smtp\SmtpSessionFactory;
use App\Mail\Value\EndpointTarget;
use App\Mail\Value\ScanBudget;
use Tests\Support\FakeSmtpSession;
use Tests\Support\FakeSmtpSessionFactory;
use Tests\TestCase;

class SmtpProbeTest extends TestCase
{
    public function test_external_recipient_acceptance_is_only_suspected_relay(): void
    {
        config(['mail_scan.relay_sender' => 'sender@scanner.test', 'mail_scan.relay_recipient' => 'recipient@external.test']);
        $session = new FakeSmtpSession(['greeting' => [220, ['mx.test ESMTP']]]);
        $this->app->instance(SmtpSessionFactory::class, new FakeSmtpSessionFactory($session));
        $probe = app(SmtpProbe::class)->probe(new EndpointTarget('mx.test', 0, '8.8.8.8', 4), new ScanBudget(30));
        $relay = array_values(array_filter($probe->findings, fn ($f) => $f->key === 'open_relay'))[0];
        $verbs = array_map(fn ($c) => explode(' ', $c)[0], $session->commands);
        $this->assertSame(Outcome::Warning, $relay->outcome);
        $this->assertNotContains('DATA', $verbs);
        $this->assertContains('RSET', $verbs);
        $this->assertContains('QUIT', $verbs);
        $this->assertTrue($session->closed);
    }

    public function test_unrelated_recipient_rejection_is_inconclusive(): void
    {
        config(['mail_scan.relay_sender' => 'sender@scanner.test', 'mail_scan.relay_recipient' => 'recipient@external.test']);
        $session = new FakeSmtpSession(['greeting' => [220, ['mx.test']], 'RCPT' => [550, ['5.1.1 Unknown recipient']]]);
        $this->app->instance(SmtpSessionFactory::class, new FakeSmtpSessionFactory($session));
        $probe = app(SmtpProbe::class)->probe(new EndpointTarget('mx.test', 0, '8.8.8.8', 4), new ScanBudget(30));
        $relay = array_values(array_filter($probe->findings, fn ($f) => $f->key === 'open_relay'))[0];
        $this->assertSame(Outcome::Inconclusive,$relay->outcome);
    }
}
