<?php
namespace Tests\Feature;
use Tests\TestCase;
use Inertia\Testing\AssertableInertia;
class HomePageTest extends TestCase {
    public function test_home_uses_inertia(): void {
        $this->withoutVite();
        $this->get('/')->assertOk()->assertInertia(fn(AssertableInertia $page) => $page->component('Home'));
    }
}
