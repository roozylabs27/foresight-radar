<?php

namespace Tests\Feature\Locale;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocaleManagementTest extends TestCase
{
    public function test_it_defaults_to_indonesian_locale(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
        $response->assertHeader('Content-Language', 'id');
        $this->assertEquals('id', app()->getLocale());
    }

    public function test_it_switches_locale_via_post_and_sets_session_and_cookie(): void
    {
        $response = $this->from('/login')->post('/locale', [
            'locale' => 'en',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHas('locale', 'en');
        $response->assertCookie('app_locale', 'en');
    }

    public function test_it_respects_cookie_locale_on_subsequent_requests(): void
    {
        $response = $this->withCookies(['app_locale' => 'en'])->get('/login');

        $response->assertOk();
        $response->assertHeader('Content-Language', 'en');
        $this->assertEquals('en', app()->getLocale());
    }

    public function test_it_rejects_unsupported_locale(): void
    {
        $response = $this->from('/login')->post('/locale', [
            'locale' => 'invalid_lang',
        ]);

        $response->assertSessionHasErrors('locale');
    }
}
