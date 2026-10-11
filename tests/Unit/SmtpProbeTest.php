<?php

namespace Tests\Unit;

use App\Mail\Checks\SmtpProbe;
use App\Mail\Enums\Outcome;
use App\Mail\Smtp\SmtpSessionFactory;
use App\Mail\Value\EndpointTarget;
use App\Mail\Value\ScanBudget;
use App\Mail\Value\TlsObservation;
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
        $probe = app(SmtpProbe::class)->probe(new EndpointTarget('mx.test', 0, '8.8.8.8', 4), new ScanBudget(30), 'fixture.test');
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
        $probe = app(SmtpProbe::class)->probe(new EndpointTarget('mx.test', 0, '8.8.8.8', 4), new ScanBudget(30), 'fixture.test');
        $relay = array_values(array_filter($probe->findings, fn ($f) => $f->key === 'open_relay'))[0];
        $this->assertSame(Outcome::Inconclusive, $relay->outcome);
    }

    public function test_ehlo_runs_again_after_starttls_and_no_envelope_after_failure(): void
    {
        $session = new FakeSmtpSession(['greeting' => [220, ['mx.test']], 'EHLO' => [250, ['mx.test', 'STARTTLS']], 'STARTTLS' => [220, ['Begin TLS']]]);
        $this->app->instance(SmtpSessionFactory::class, new FakeSmtpSessionFactory($session));
        app(SmtpProbe::class)->probe(new EndpointTarget('mx.test', 0, '8.8.8.8', 4), new ScanBudget(30), 'fixture.test');
        $verbs = array_map(fn ($c) => explode(' ', $c)[0], $session->commands);
        $this->assertSame(2, count(array_filter($verbs, fn ($v) => $v === 'EHLO')));
        $failed = new class(['greeting' => [220, ['mx.test']], 'EHLO' => [250, ['mx.test', 'STARTTLS']], 'STARTTLS' => [220, ['Begin TLS']]]) extends FakeSmtpSession
        {
            public function startTls(string $mx, ?int $protocolMask = null): TlsObservation
            {
                return new TlsObservation(false, error: 'Handshake failed.');
            }
        };
        $this->app->instance(SmtpSessionFactory::class, new FakeSmtpSessionFactory($failed));
        app(SmtpProbe::class)->probe(new EndpointTarget('mx.test', 0, '8.8.8.8', 4), new ScanBudget(30), 'fixture.test');
        $this->assertNotContains('MAIL', array_map(fn ($c) => explode(' ', $c)[0], $failed->commands));
    }
}
