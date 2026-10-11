<?php

namespace Tests\Feature;

use App\Mail\Scans\ReportAssessment;
use App\Mail\Scans\ScanRepository;
use App\Mail\Value\Finding;
use App\Models\Scan;
use App\Models\ScanEndpoint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportCompletenessTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_or_failed_endpoints_cannot_receive_dimension_passes(): void
    {
        foreach (['queued', 'expired'] as $status) {
            $scan = Scan::factory()->create(['status' => 'running']);
            ScanEndpoint::factory()->for($scan)->create(['status' => $status]);
            $repo = app(ScanRepository::class);
            $repo->putFinding($scan, null, Finding::make('mx', 'pass', 'MX published'));
            $repo->putFinding($scan, null, Finding::make('mtasts', 'info', 'No MTA-STS'));
            $result = app(ReportAssessment::class)->assess($scan);
            foreach (['reachability', 'transport'] as $key) {
                $this->assertSame('Incomplete', $result['dimensions'][$key]['label']);
            }
        }
    }

    public function test_policy_information_without_transport_observations_cannot_pass(): void
    {
        $scan = Scan::factory()->create(['status' => 'completed']);
        app(ScanRepository::class)->putFinding($scan, null, Finding::make('mtasts', 'info', 'No MTA-STS'));
        $this->assertSame('Incomplete', app(ReportAssessment::class)->assess($scan)['dimensions']['transport']['label']);
    }

    public function test_null_mx_preserves_dns_failures_and_dns_incompleteness(): void
    {
        $scan = Scan::factory()->create(['status' => 'completed', 'routing_snapshot' => ['null_mx' => true]]);
        app(ScanRepository::class)->putFinding($scan, null, Finding::make('spf', 'fail', 'Authorizes all senders', '', [], null, 'high'));
        $assessment = app(ReportAssessment::class)->assess($scan);
        $this->assertSame('Attention needed', $assessment['overall']);
        $this->assertSame('Not applicable', $assessment['dimensions']['reachability']['label']);
        $scan = Scan::factory()->create(['status' => 'partial', 'routing_snapshot' => ['null_mx' => true]]);
        app(ScanRepository::class)->putFinding($scan, null, Finding::make('dmarc', 'inconclusive', 'DNS timed out'));
        $assessment = app(ReportAssessment::class)->assess($scan);
        $this->assertSame('Incomplete', $assessment['overall']);
        $this->assertTrue($assessment['coverage']['incomplete']);
    }
}
