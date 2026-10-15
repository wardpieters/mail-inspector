<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PrivacyPageTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('privacyPageSettings')]
    public function test_privacy_page_uses_the_configured_retention_and_browser_language(int $retentionDays, string $language, string $locale, string $title): void
    {
        $this->withoutVite();
        config(['mail_scan.retention_days' => $retentionDays]);

        $this->get('/privacy', ['Accept-Language' => $language])->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Privacy')
                ->where('retention_days', $retentionDays)
                ->where('i18n.locale', $locale)
                ->where('i18n.messages.Privacy policy', $title));
    }

    public static function privacyPageSettings(): array
    {
        return [
            'English with default retention' => [7, 'en-US', 'en_US', 'Privacy policy'],
            'Dutch with custom retention' => [14, 'nl-NL', 'nl', 'Privacybeleid'],
        ];
    }

    public function test_privacy_page_uses_the_configured_contact_details(): void
    {
        $this->withoutVite();
        config([
            'privacy.contact_name' => 'Example Operator',
            'privacy.contact_email' => 'privacy@example.org',
        ]);

        $this->get('/privacy')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Privacy')
                ->where('contact.name', 'Example Operator')
                ->where('contact.email', 'privacy@example.org'));
    }

    public function test_home_page_shares_named_navigation_links_for_the_footer(): void
    {
        $this->withoutVite();

        $this->get('/')->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('navigation.home', '/')
                ->where('navigation.privacy', '/privacy'));
    }
}
