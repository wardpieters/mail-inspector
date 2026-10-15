<?php

namespace Tests\Feature;

use App\Models\Scan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class ScanSubmissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    #[TestWith([true])]
    #[TestWith([false])]
    public function test_submission_respects_public_overview_visibility(bool $hidden): void
    {
        $this->withoutVite();

        $this->post('/scans', ['domain' => 'example.com', 'hidden_from_overview' => $hidden])
            ->assertSessionHasNoErrors()->assertRedirect();

        $scan = Scan::sole();
        $this->assertSame($hidden, $scan->hidden_from_overview);
        $this->get('/')->assertInertia(fn (Assert $page) => $page->has('recent_scans', $hidden ? 0 : 1));
        $this->get(route('scans.show', $scan->public_id))->assertOk();
    }

    public function test_submissions_are_public_by_default(): void
    {
        $this->post('/scans', ['domain' => 'example.com'])->assertRedirect();

        $this->assertSame(false, Scan::sole()->hidden_from_overview);
    }

    public function test_hiding_a_reused_report_cannot_be_undone_by_another_submission(): void
    {
        $this->withoutVite();
        $this->post('/scans', ['domain' => 'example.com'])->assertRedirect();
        $scan = Scan::sole();

        $this->post('/scans', ['domain' => 'example.com', 'hidden_from_overview' => true])
            ->assertRedirect(route('scans.show', $scan->public_id));
        $this->assertTrue($scan->fresh()->hidden_from_overview);
        $this->post('/scans', ['domain' => 'example.com', 'hidden_from_overview' => false])
            ->assertRedirect(route('scans.show', $scan->public_id));

        $this->assertDatabaseCount('scans', 1);
        $this->assertTrue($scan->fresh()->hidden_from_overview);
        $this->get('/')->assertInertia(fn (Assert $page) => $page->has('recent_scans', 0));
    }

    public function test_invalid_visibility_is_rejected_without_creating_a_scan(): void
    {
        $this->post('/scans', ['domain' => 'example.com', 'hidden_from_overview' => 'invalid'])
            ->assertSessionHasErrors('hidden_from_overview');

        $this->assertDatabaseCount('scans', 0);
        Queue::assertNothingPushed();
    }

    public function test_empty_selector_text_submits_without_selectors(): void
    {
        $this->post('/scans', ['domain' => 'example.com', 'selectors' => ''])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseHas('scans', ['domain' => 'example.com', 'selectors' => '[]']);
    }

    public function test_submits_normalized_domain_with_outbox(): void
    {
        $this->post('/scans', ['domain' => 'EXAMPLE.COM.', 'selectors' => []])->assertRedirect();
        $this->assertDatabaseHas('scans', ['domain' => 'example.com', 'status' => 'queued']);
        $this->assertDatabaseCount('scan_outbox', 1);
    }

    public function test_same_idna_domain_reuses_active_report_without_changing_selectors(): void
    {
        $a = $this->post('/scans', ['domain' => 'bücher.example', 'selectors' => ['first']]);
        $b = $this->post('/scans', ['domain' => 'xn--bcher-kva.example', 'selectors' => ['second']]);
        $this->assertSame($a->headers->get('Location'), $b->headers->get('Location'));
        $this->assertDatabaseCount('scans', 1);
        $this->assertSame(['first'], json_decode(\DB::table('scans')->value('selectors'), true));
    }

    #[TestWith(['completed'])]
    #[TestWith(['partial'])]
    #[TestWith(['failed'])]
    public function test_reuses_recent_terminal_report_across_visitors_without_extending_cooldown(string $status): void
    {
        $this->freezeTime();
        $scan = Scan::factory()->create(['domain' => 'xn--bcher-kva.example', 'status' => $status, 'selectors' => ['first']]);
        $this->travel(179)->seconds();

        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.10'])
            ->post('/scans', ['domain' => 'BÜCHER.EXAMPLE.', 'selectors' => ['second']])
            ->assertRedirect(route('scans.show', $scan->public_id));

        $this->assertDatabaseCount('scans', 1);
        $this->assertDatabaseCount('scan_outbox', 0);
        $this->assertSame(['first'], $scan->fresh()->selectors);
        Queue::assertNothingPushed();

        $this->travel(1)->seconds();
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.11'])
            ->post('/scans', ['domain' => 'xn--bcher-kva.example', 'selectors' => ['second']])
            ->assertRedirect(route('scans.show', Scan::latest('id')->first()->public_id));

        $this->assertDatabaseCount('scans', 2);
        $this->assertDatabaseCount('scan_outbox', 1);
        $this->assertSame(['second'], Scan::latest('id')->first()->selectors);
    }

    public function test_a_running_scan_is_reused_after_the_cooldown_until_its_deadline(): void
    {
        $this->freezeTime();
        $scan = Scan::factory()->running()->create(['domain' => 'example.com']);
        $this->travel(180)->seconds();

        $this->post('/scans', ['domain' => 'example.com'])
            ->assertRedirect(route('scans.show', $scan->public_id));

        $this->assertDatabaseCount('scans', 1);
        $this->assertDatabaseCount('scan_outbox', 0);
        Queue::assertNothingPushed();
    }

    public function test_a_recent_scan_for_another_domain_does_not_block_a_new_scan(): void
    {
        $this->freezeTime();
        Scan::factory()->create(['domain' => 'another.example', 'status' => 'completed']);

        $this->post('/scans', ['domain' => 'example.com'])->assertRedirect();

        $this->assertDatabaseHas('scans', ['domain' => 'example.com', 'status' => 'queued']);
        $this->assertDatabaseCount('scans', 2);
        $this->assertDatabaseCount('scan_outbox', 1);
    }

    public function test_report_countdown_uses_the_original_submission_time_after_a_redirect_or_reload(): void
    {
        $this->withoutVite();
        $this->travelTo(now()->setDate(2026, 10, 15)->setTime(12, 0));
        $scan = Scan::factory()->create(['domain' => 'example.com', 'status' => 'completed']);
        $this->travel(120)->seconds();
        $this->post('/scans', ['domain' => 'example.com'])->assertRedirect(route('scans.show', $scan->public_id));

        $this->get(route('scans.show', $scan->public_id))->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('cooldown.available_at', '2026-10-15T12:03:00+00:00')
                ->where('cooldown.server_now', '2026-10-15T12:02:00+00:00'));
    }

    public function test_rejects_unsafe_inputs_and_excess_selectors(): void
    {
        foreach (['https://example.com', '127.0.0.1', "bad\r\nexample.com", 'name@example.com', 'a..com'] as $i => $domain) {
            $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.'.($i + 1)])->post('/scans', ['domain' => $domain])->assertSessionHasErrors('domain');
        }
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.99'])->post('/scans', ['domain' => 'example.com', 'selectors' => array_map(fn ($i) => 's'.$i, range(1, 11))])->assertSessionHasErrors('selectors');
    }

    public function test_sixth_submission_is_rate_limited(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/scans', ['domain' => 'example.com'])->assertRedirect();
        } $this->post('/scans', ['domain' => 'example.com'])->assertStatus(429);
    }

    public function test_unknown_report_returns_404(): void
    {
        $this->get('/scans/00000000-0000-4000-8000-000000000000')->assertNotFound();
    }
}
