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

    public function test_the_main_site_opens_the_school_code_box_to_reach_the_platform(): void
    {
        $this->get('http://localhost/')
            ->assertOk()
            ->assertSee('data-open-school', false)
            ->assertSee('id="school-dialog"', false)
            ->assertSee('ادخل المنصة')
            ->assertSee('landing/entry.js', false)
            ->assertSee('.'.config('tenancy.base_domain'), false);
    }

    public function test_a_school_address_goes_straight_to_its_own_sign_in(): void
    {
        $this->get('http://demo.mudarisy.test/')
            ->assertOk()
            ->assertSee('class="btn btn-gold" href="/login"', false)
            ->assertDontSee('id="school-dialog"', false)
            ->assertDontSee('data-open-school', false);
    }

    public function test_a_configured_web_address_replaces_the_school_code_box(): void
    {
        config(['landing.web_url' => 'https://app.example.com']);

        $this->get('http://localhost/')
            ->assertOk()
            ->assertSee('class="btn btn-gold" href="https://app.example.com"', false)
            ->assertDontSee('id="school-dialog"', false);
    }

    public function test_the_first_screen_is_sized_for_the_header_the_hero_and_the_moving_strip(): void
    {
        $this->get('http://localhost/')
            ->assertOk()
            ->assertSee('--marquee-h', false)
            ->assertSee('class="marquee"', false)
            ->assertSee('id="nav-toggle"', false);
    }
}
