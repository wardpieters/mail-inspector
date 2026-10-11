<?php

namespace Tests\Feature;

use App\Mail\Scans\ScanCleaner;
use App\Models\Scan;
use App\Models\ScanEndpoint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScanRetentionTest extends TestCase
{
    use RefreshDatabase;

    public function test_prunes_old_scan_with_dependent_endpoints(): void
    {
        $scan = Scan::factory()->create(['created_at' => now()->subDays(8)]);
        $endpoint = ScanEndpoint::factory()->for($scan)->create();
        $this->assertSame(1, app(ScanCleaner::class)->prune());
        $this->assertModelMissing($scan);
        $this->assertModelMissing($endpoint);
    }
}
