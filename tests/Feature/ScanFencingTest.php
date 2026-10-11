<?php

namespace Tests\Feature;

use App\Jobs\DiscoverScan;
use App\Mail\Dns\DnsClient;
use App\Mail\Enums\ScanStatus;
use App\Mail\Scans\ScanCoordinator;
use App\Models\Scan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\Support\FakeDnsClient;
use Tests\TestCase;

class ScanFencingTest extends TestCase
{
    use RefreshDatabase;

    private function interleaveBeforeNextScanUpdate(Scan $scan, array $changes): void
    {
        $fired = false;
        DB::connection()->beforeExecuting(function (string $query) use ($scan, $changes, &$fired) {
            if (! $fired && str_starts_with($query, 'update "scans"')) {
                $fired = true;
                DB::table('scans')->where('id', $scan->id)->update($changes);
            }
        });
    }

    public function test_discovery_start_cannot_reopen_a_scan_finalized_between_read_and_write(): void
    {
        Queue::fake();
        $this->app->instance(DnsClient::class, new FakeDnsClient);
        $scan = Scan::factory()->create();
        $this->interleaveBeforeNextScanUpdate($scan, ['status' => 'failed', 'finished_at' => now(), 'terminal_error' => 'Deadline reached.']);
        app(ScanCoordinator::class)->discover($scan);
        $this->assertSame(ScanStatus::Failed, $scan->fresh()->status);
        $this->assertNull($scan->fresh()->routing_snapshot);
    }

    public function test_discovery_failure_cannot_overwrite_a_newly_completed_report(): void
    {
        $scan = Scan::factory()->create();
        $this->interleaveBeforeNextScanUpdate($scan, ['status' => 'completed', 'routing_snapshot' => json_encode(['null_mx' => true]), 'finished_at' => now()]);
        (new DiscoverScan($scan->id))->failed(new \RuntimeException('Stale job.'));
        $this->assertSame(ScanStatus::Completed, $scan->fresh()->status);
        $this->assertNull($scan->fresh()->terminal_error);
    }
}
