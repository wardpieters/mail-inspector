<?php

namespace Tests\Feature;

use App\Mail\Scans\ScanRepository;
use App\Mail\Value\Finding;
use App\Models\Scan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BrowserLocaleTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('browserLanguages')]
    public function test_selects_a_supported_browser_language_with_us_english_fallback(string $header, string $locale, string $label): void
    {
        $this->withoutVite();
        $this->get('/', ['Accept-Language' => $header])->assertOk()
            ->assertHeader('Content-Language', str_replace('_', '-', $locale))
            ->assertSee('lang="'.str_replace('_', '-', $locale).'"', false)
            ->assertInertia(fn (Assert $page) => $page->where('i18n.locale', $locale)->where('i18n.messages.Domain to inspect', $label));
    }

    public static function browserLanguages(): array
    {
        return [
            'Dutch regional preference' => ['nl-NL,nl;q=0.9,en-US;q=0.8', 'nl', 'Te controleren domein'],
            'US English' => ['en-US,en;q=0.9,nl;q=0.5', 'en_US', 'Domain to inspect'],
            'English regional fallback' => ['en-GB,en;q=0.9', 'en_US', 'Domain to inspect'],
            'weighted supported preference' => ['fr;q=1,en-US;q=0.2,nl;q=0.8', 'nl', 'Te controleren domein'],
            'unsupported language' => ['ja-JP', 'en_US', 'Domain to inspect'],
            'missing preference' => ['', 'en_US', 'Domain to inspect'],
            'explicitly rejected language' => ['nl;q=0,en-US;q=0.8', 'en_US', 'Domain to inspect'],
        ];
    }

    public function test_existing_report_is_translated_per_viewer_without_changing_evidence_or_storage(): void
    {
        $scan = Scan::factory()->create();
        app(ScanRepository::class)->putFinding($scan, null, Finding::make('dnssec', 'pass', 'DNSSEC validated', 'The controlled validating resolver authenticated this routing answer.', ['reply' => 'mail-inspector-accept.invalid 220 Ready']));
        $url = '/scans/'.$scan->public_id.'/export';
        $this->get($url, ['Accept-Language' => 'nl'])->assertOk()
            ->assertJsonPath('domain_findings.0.title', 'DNSSEC gevalideerd')
            ->assertJsonPath('domain_findings.0.explanation', 'De beheerde validerende resolver heeft dit routeringsantwoord geauthenticeerd.')
            ->assertJsonPath('domain_findings.0.evidence.reply', 'mail-inspector-accept.invalid 220 Ready')
            ->assertJsonPath('assessment.overall', 'Beoordeling onvolledig');
        $this->get($url, ['Accept-Language' => 'en-US'])->assertOk()->assertJsonPath('domain_findings.0.title', 'DNSSEC validated');
        $this->assertSame('DNSSEC validated', $scan->findings()->first()->title);
    }

    public function test_domain_and_form_validation_use_the_browser_language(): void
    {
        $this->postJson('/scans', ['domain' => 'user@example.com'], ['Accept-Language' => 'nl'])->assertUnprocessable()
            ->assertJsonPath('errors.domain.0', 'Voer een domeinnaam in, zonder URL of e-mailadres.');
        $this->postJson('/scans', [], ['Accept-Language' => 'nl'])->assertUnprocessable()
            ->assertJsonPath('errors.domain.0', 'Het domein is verplicht.');
    }

    public function test_polling_keeps_translations_in_sync_with_a_changed_browser_preference(): void
    {
        $this->withoutVite();
        $version = $this->get('/')->viewData('page')['version'];
        $scan = Scan::factory()->create();
        $this->get('/scans/'.$scan->public_id, [
            'Accept-Language' => 'nl', 'X-Inertia' => 'true',
            'X-Inertia-Version' => $version,
            'X-Inertia-Partial-Component' => 'Scans/Show', 'X-Inertia-Partial-Data' => 'report',
        ])->assertOk()->assertJsonPath('props.i18n.locale', 'nl')
            ->assertJsonPath('props.report.assessment.overall', 'Beoordeling onvolledig');
    }
}
