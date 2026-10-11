<?php

namespace Tests\Feature;

use App\Http\Resources\ScanReportResource;
use App\Models\Scan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScanExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_exports_shared_resource_without_internal_identifiers(): void
    {
        $scan = Scan::factory()->create();
        $response = $this->get('/scans/'.$scan->public_id.'/export');
        $response->assertOk()->assertJsonPath('scan.domain', 'fixture.test')->assertJsonMissingPath('scan.id');
        $this->assertSame((new ScanReportResource($scan))->resolve(), $response->json());
    }
}
