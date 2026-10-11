<?php

namespace Tests\Integration;

use App\Jobs\ProbeEndpoint;
use App\Mail\Checks\EndpointInspector;
use App\Mail\Dns\DnsClient;
use App\Mail\Network\ProbePermits;
use App\Mail\Scans\ScanCoordinator;
use App\Mail\Scans\ScanRepository;
use App\Mail\Smtp\SmtpSessionFactory;
use App\Models\Scan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Support\CertificateFixtures;
use Tests\Support\FakeDnsClient;
use Tests\Support\FixtureSmtpSessionFactory;
use Tests\Support\SmtpSocketFixture;
use Tests\TestCase;

class DualStackScanTest extends TestCase
{
    use RefreshDatabase;

    public function test_ipv4_and_ipv6_are_connected_independently_through_the_full_job_pipeline(): void
    {
        if (! env('MAIL_TEST_INTEGRATION')) {
            $this->markTestSkipped('Requires isolated Redis fixture.');
        }Queue::fake();
        $v4 = new SmtpSocketFixture;
        try {
            $v6 = new SmtpSocketFixture(6, 'refuse');
        } catch (\RuntimeException $e) {
            $v4->close();
            $this->markTestSkipped('IPv6 loopback fixture unavailable: '.$e->getMessage());
        }
        try {
            $dns = new FakeDnsClient;
            $dns->add('fixture.test', 'MX', [['exchange' => 'mx.test', 'preference' => 10]]);
            $dns->add('mx.test', 'A', [['address' => '8.8.8.8']]);
            $dns->add('mx.test', 'AAAA', [['address' => '2606:4700:4700::1111']]);
            $this->app->instance(DnsClient::class, $dns);
            $this->app->instance(SmtpSessionFactory::class, new FixtureSmtpSessionFactory([4 => $v4, 6 => $v6]));
            config(['mail_scan.ca_file' => CertificateFixtures::caFile()]);
            $this->post('/scans', ['domain' => 'fixture.test'])->assertRedirect();
            $scan = Scan::sole();
            app(ScanCoordinator::class)->discover($scan);
            foreach ($scan->endpoints as $endpoint) {
                (new ProbeEndpoint($endpoint->id))->handle(app(ScanRepository::class), app(ProbePermits::class), app(EndpointInspector::class));
            }
            $this->assertSame([4, 6], $scan->endpoints()->orderBy('family')->pluck('family')->all());
            $this->assertSame(2, $scan->endpoints()->where('status', 'completed')->count());
            $report = $this->get('/scans/'.$scan->public_id.'/export')->assertOk()->json();
            $this->assertSame('pass', collect($report['endpoints'][0]['findings'])->firstWhere('key', 'answer')['outcome']);
            $this->assertSame('fail', collect($report['endpoints'][1]['findings'])->firstWhere('key', 'answer')['outcome']);
            $this->assertSame('Attention needed', $report['assessment']['overall']);
            foreach (['dnssec', 'caa', 'answer', 'connect', 'helo', 'tls', 'secure', 'cert', 'from', 'mtasts', 'dane', 'fcrdns', 'open_relay', 'spf', 'dkim', 'dmarc'] as $key) {
                $this->assertContains($key, $scan->findings()->pluck('check_key')->all());
            }
            $this->assertNotContains('DATA', array_column($v4->observations(), 'verb'));
        } finally {
            $v4->close();
            $v6->close();
        }
    }
}
