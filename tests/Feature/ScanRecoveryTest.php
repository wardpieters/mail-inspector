<?php

namespace Tests\Feature;

use App\Mail\Enums\ScanStatus;
use App\Mail\Scans\ScanRepository;
use App\Mail\Scans\ScanWatchdog;
use App\Mail\Value\Finding;
use App\Models\Scan;
use App\Models\ScanEndpoint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScanRecoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_late_endpoint_job_cannot_reopen_finalized_scan(): void
    {
        $scan = Scan::factory()->partial()->create();
        $endpoint = ScanEndpoint::factory()->for($scan)->create(['status' => 'running', 'lease_token' => 'stale']);
        app(ScanRepository::class)->completeEndpoint($endpoint, 'stale', [Finding::make('connect', 'pass', 'Late observation')]);
        $this->assertSame(ScanStatus::Partial, $scan->fresh()->status);
        $this->assertSame(0, $scan->findings()->count());
    }

    public function test_watchdog_expires_stalled_work_into_partial_report(): void
    {
        $scan = Scan::factory()->running()->create(['deadline' => now()->subSecond()]);
        ScanEndpoint::factory()->for($scan)->create(['status' => 'running', 'lease_token' => 'dead', 'lease_expires_at' => now()->subSecond()]);
        app(ScanRepository::class)->putFinding($scan, null, Finding::make('mx', 'pass', 'Routing discovered'));
        app(ScanWatchdog::class)->reconcile();
        $this->assertSame(ScanStatus::Partial, $scan->fresh()->status);
        $this->assertSame(1, $scan->endpoints()->where('status', 'expired')->count());
    }

    public function test_claim_cannot_be_taken_twice_and_wrong_owner_cannot_complete(): void
    {
        $scan = Scan::factory()->running()->create();
        $endpoint = ScanEndpoint::factory()->for($scan)->create();
        $repository = app(ScanRepository::class);
        $token = $repository->claimEndpoint($endpoint);
        $this->assertNotNull($token);
        $this->assertNull($repository->claimEndpoint($endpoint));
        $repository->completeEndpoint($endpoint, 'wrong', [Finding::make('connect', 'pass', 'Wrong worker')]);
        $this->assertSame(0, $scan->findings()->count());
    }
}
