<?php

namespace Tests\Feature;

use Tests\TestCase;

class LandingPageTest extends TestCase
{
    public function test_home_renders_the_landing_page(): void
    {
        config(['landing.name' => 'Mudarisy']);

        $this->get('/')
            ->assertOk()
            ->assertSee('dir="rtl"', false)
            ->assertSee('Mudarisy')
            ->assertSee('أندرويد — قريباً');
    }

    public function test_store_links_replace_the_soon_buttons_when_configured(): void
    {
        config(['landing.android_url' => 'https://example.com/android']);

        $this->get('/')
            ->assertOk()
            ->assertSee('href="https://example.com/android"', false)
            ->assertDontSee('أندرويد — قريباً');
    }
}
