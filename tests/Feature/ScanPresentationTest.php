<?php

namespace Tests\Feature;

use App\Mail\Scans\ScanRepository;
use App\Mail\Value\Finding;
use App\Models\Scan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScanPresentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_waiting_summaries_are_distinguished_from_final_incomplete_assessments(): void
    {
        $scan = Scan::factory()->create();
        $url = '/scans/'.$scan->public_id.'/export';
        $this->get($url)->assertOk()->assertJsonPath('assessment.pending', true)
            ->assertJsonPath('assessment.dimensions.transport.pending', true);
        $scan->update(['status' => 'partial']);
        $this->get($url)->assertOk()->assertJsonPath('assessment.pending', false)
            ->assertJsonPath('assessment.dimensions.transport.pending', false)
            ->assertJsonPath('assessment.dimensions.transport.label', 'Assessment incomplete');
    }

    public function test_pending_scan_does_not_hide_observed_security_failures(): void
    {
        $scan = Scan::factory()->running()->create();
        app(ScanRepository::class)->putFinding($scan, null, Finding::make('spf', 'fail', 'SPF authorizes every sender', '', [], null, 'high'));
        $this->get('/scans/'.$scan->public_id.'/export')->assertOk()
            ->assertJsonPath('assessment.pending', false)
            ->assertJsonPath('assessment.overall', 'Attention needed')
            ->assertJsonPath('assessment.dimensions.sender_authentication.pending', false)
            ->assertJsonPath('assessment.dimensions.sender_authentication.label', 'Attention needed')
            ->assertJsonPath('assessment.dimensions.transport.pending', true);
    }
}
