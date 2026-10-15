<?php

namespace Tests\Feature;

use App\Models\Scan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class HomePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_uses_inertia(): void
    {
        $this->withoutVite();
        $this->get('/')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->component('Home')->has('recent_scans', 0));
    }

    public function test_home_lists_the_latest_five_unexpired_public_reports(): void
    {
        $this->withoutVite();
        $this->freezeTime();
        config(['mail_scan.retention_days' => 3]);
        Scan::factory()->create(['created_at' => now()->subDays(3)]);
        Scan::factory()->create(['hidden_from_overview' => true]);
        $reports = collect(range(6, 1))->map(fn (int $minutes) => Scan::factory()->create([
            'domain' => "domain{$minutes}.example",
            'created_at' => now()->subMinutes($minutes),
        ]))->reverse()->values();

        $this->get('/')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->has('recent_scans', 5)
            ->where('recent_scans.0.domain', 'domain1.example')
            ->where('recent_scans.4.domain', 'domain5.example')
            ->where('recent_scans.0.status', 'queued')
            ->where('recent_scans.0.created_at', $reports[0]->created_at->toIso8601String())
            ->where('recent_scans.0.url', route('scans.show', $reports[0]->public_id)));
    }
}
