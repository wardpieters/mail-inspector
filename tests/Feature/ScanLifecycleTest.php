<?php

namespace Tests\Feature;

use App\Mail\Checks\EndpointInspector;
use App\Mail\Dns\DnsClient;
use App\Mail\Enums\ScanStatus;
use App\Mail\Scans\ScanCoordinator;
use App\Mail\Scans\ScanFinalizer;
use App\Mail\Scans\ScanRepository;
use App\Mail\Smtp\SmtpSessionFactory;
use App\Mail\Value\EndpointTarget;
use App\Mail\Value\PolicySnapshot;
use App\Mail\Value\ScanBudget;
use App\Models\Scan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Support\FakeDnsClient;
use Tests\Support\FakeSmtpSessionFactory;
use Tests\TestCase;

class ScanLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_discovery_plans_dual_stack_endpoints_and_persists_all_probe_checks(): void
    {
        Queue::fake();
        $dns = new FakeDnsClient;
        $dns->add('fixture.test', 'MX', [['exchange' => 'mx.test', 'preference' => 10]]);
        $dns->add('mx.test', 'A', [['address' => '8.8.8.8']]);
        $dns->add('mx.test', 'AAAA', [['address' => '2606:4700:4700::1111']]);
        $this->app->instance(DnsClient::class, $dns);
        $this->app->instance(SmtpSessionFactory::class, new FakeSmtpSessionFactory);
        $scan = Scan::factory()->create();
        app(ScanCoordinator::class)->discover($scan);
        $this->assertSame([4, 6], $scan->endpoints()->orderBy('family')->pluck('family')->all());
        foreach ($scan->endpoints as $endpoint) {
            $token = app(ScanRepository::class)->claimEndpoint($endpoint);
            $findings = app(EndpointInspector::class)->inspect(EndpointTarget::fromArray($endpoint->toArray()), PolicySnapshot::fromArray($scan->fresh()->policy_snapshot), new ScanBudget(30));
            app(ScanRepository::class)->completeEndpoint($endpoint, $token, $findings);
        }
        app(ScanFinalizer::class)->finalize($scan);
        $this->assertSame(ScanStatus::Completed, $scan->fresh()->status);
        $this->assertSame(2, $scan->endpoints()->where('status', 'completed')->count());
        $this->assertContains('dane', $scan->findings()->pluck('check_key')->all());
    }

    public function test_null_mx_finalizes_without_jobs_for_endpoints(): void
    {
        Queue::fake();
        $dns = new FakeDnsClient;
        $dns->add('fixture.test', 'MX', [['exchange' => '.', 'preference' => 0]]);
        $this->app->instance(DnsClient::class, $dns);
        $scan = Scan::factory()->create();
        app(ScanCoordinator::class)->discover($scan);
        $this->assertSame(ScanStatus::Completed, $scan->fresh()->status);
        $this->assertSame(0, $scan->endpoints()->count());
        $this->assertTrue($scan->fresh()->routing_snapshot['null_mx']);
    }
}
