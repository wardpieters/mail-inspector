<?php

namespace Tests\Integration;

use App\Mail\Checks\CertificateCheck;
use App\Mail\Checks\SmtpProbe;
use App\Mail\Enums\Outcome;
use App\Mail\Smtp\SmtpSessionFactory;
use App\Mail\Value\EndpointTarget;
use App\Mail\Value\ScanBudget;
use Tests\Support\CertificateFixtures;
use Tests\Support\FixtureSmtpSessionFactory;
use Tests\Support\SmtpSocketFixture;
use Tests\TestCase;

class StartTlsTest extends TestCase
{
    public function test_real_socket_negotiates_tls_sends_mx_sni_and_repeats_ehlo_without_data(): void
    {
        $fixture = new SmtpSocketFixture;
        try {
            $this->app->instance(SmtpSessionFactory::class, new FixtureSmtpSessionFactory([4 => $fixture]));
            $target = new EndpointTarget('mx.test', 10, '8.8.8.8', 4, true);
            $budget = new ScanBudget(30);
            $probe = app(SmtpProbe::class)->probe($target, $budget);
            $this->assertTrue($probe->tls?->success ?? false);
            $this->assertNotEmpty($probe->tls->chainPem);
            config(['mail_scan.ca_file' => CertificateFixtures::caFile()]);
            $this->assertSame(Outcome::Pass, app(CertificateCheck::class)->analyze($target, $probe->tls->chainPem, $budget)->outcome);
            $observations = $fixture->observations();
            $this->assertContains('mx.test', array_column($observations, 'sni'));
            $verbs = array_column($observations, 'verb');
            $this->assertSame(2, count(array_filter($verbs, fn ($v) => $v === 'EHLO')));
            $this->assertNotContains('DATA', $verbs);
        } finally {
            $fixture->close();
        }
    }
}
