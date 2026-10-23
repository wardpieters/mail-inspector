<?php

namespace Tests\Feature;

use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class HeaderAnalyzerPageTest extends TestCase
{
    #[DataProvider('languages')]
    public function test_serves_the_local_analyzer_with_named_navigation_and_browser_language(string $language, string $locale, string $title, string $privacy): void
    {
        $this->withoutVite();

        $this->get('/headers', ['Accept-Language' => $language])->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Headers/Show')
                ->where('navigation.home', route('home', absolute: false))
                ->where('navigation.headers', route('headers.show', absolute: false))
                ->where('navigation.privacy', route('privacy', absolute: false))
                ->where('i18n.locale', $locale)
                ->where('i18n.messages', fn (Collection $messages): bool => $messages['Message Header Analyzer'] === $title && $messages['Your headers stay in your browser.'] === $privacy)
                ->missing('headers')
                ->missing('analysis'));
    }

    public static function languages(): array
    {
        return [
            'English' => ['en-US', 'en_US', 'Message Header Analyzer', 'Your headers stay in your browser.'],
            'Dutch' => ['nl-NL', 'nl', 'Berichtkoppen analyseren', 'Je berichtkoppen blijven in je browser.'],
        ];
    }
}
