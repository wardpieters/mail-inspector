<?php

namespace Tests\Feature;

use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class HomePageTest extends TestCase
{
    public function test_home_uses_inertia(): void
    {
        $this->withoutVite();
        $this->get('/')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->component('Home'));
    }
}
