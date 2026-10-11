<?php

namespace Tests\Feature;

use App\Mail\Scans\ReportAssessment;
use App\Mail\Scans\ScanRepository;
use App\Mail\Value\Finding;
use App\Models\Scan;
use App\Models\ScanEndpoint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ScanReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_backup_ipv6_failure_remains_visible_and_controls_assessment(): void
    {
        $this->withoutVite();
        $scan = Scan::factory()->running()->create();
        $v4 = ScanEndpoint::factory()->for($scan)->create(['status' => 'completed']);
        $v6 = ScanEndpoint::factory()->for($scan)->create(['ip' => '2606:4700:4700::1111', 'family' => 6, 'status' => 'completed', 'preference' => 20]);
        $repository = app(ScanRepository::class);
        $repository->putFinding($scan, $v4, Finding::make('connect', 'pass', 'IPv4 works'));
        $repository->putFinding($scan, $v6, Finding::make('connect', 'fail', 'IPv6 refused', '', [], null, 'high'));
        $this->get('/scans/'.$scan->public_id)->assertOk()->assertInertia(fn (Assert $page) => $page->component('Scans/Show')->where('report.assessment.overall', 'Attention needed')->has('report.endpoints', 2)->where('report.endpoints.1.family', 6));
    }

    public function test_null_mx_has_clear_precedence_and_expired_report_is_404(): void
    {
        $scan = Scan::factory()->create(['status' => 'completed', 'routing_snapshot' => ['null_mx' => true, 'omitted' => 0]]);
        $this->assertSame('Mail intentionally disabled', app(ReportAssessment::class)->assess($scan)['overall']);
        $scan->update(['created_at' => now()->subDays(8)]);
        $this->get('/scans/'.$scan->public_id)->assertNotFound();
        $this->get('/scans/'.$scan->id)->assertNotFound();
    }
}
