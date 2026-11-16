<?php

namespace Tests\Feature\Localization;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SetLocaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_locale_is_english_for_first_visit_without_cookie_or_session(): void
    {
        $response = $this->get('/players');

        $response->assertOk();
        $response->assertSee('<html lang="en">', false);
        $response->assertSee(__('players.index.title', [], 'en'));
        $response->assertHeader('Vary', 'Cookie');
    }

    public function test_cookie_activates_russian_locale(): void
    {
        $response = $this->withCookie('locale', 'ru')->get('/players');

        $response->assertOk();
        $response->assertSee('<html lang="ru">', false);
        $response->assertSee(__('players.index.title', [], 'ru'));
    }

    public function test_session_activates_russian_locale(): void
    {
        $response = $this->withSession(['locale' => 'ru'])->get('/players');

        $response->assertOk();
        $response->assertSee('<html lang="ru">', false);
        $response->assertSee(__('players.index.title', [], 'ru'));
    }

    public function test_session_takes_priority_over_cookie(): void
    {
        $response = $this
            ->withCookie('locale', 'ru')
            ->withSession(['locale' => 'en'])
            ->get('/players');

        $response->assertOk();
        $response->assertSee('<html lang="en">', false);
        $response->assertSee(__('players.index.title', [], 'en'));
        $response->assertDontSee(__('players.index.title', [], 'ru'));
    }

    public function test_unknown_locale_in_cookie_or_session_falls_back_to_english(): void
    {
        $cookieResponse = $this->withCookie('locale', 'xx')->get('/players');
        $cookieResponse->assertOk();
        $cookieResponse->assertSee('<html lang="en">', false);

        $sessionResponse = $this->withSession(['locale' => 'de'])->get('/players');
        $sessionResponse->assertOk();
        $sessionResponse->assertSee('<html lang="en">', false);
    }

    public function test_switching_to_unknown_locale_returns_404_and_preserves_current_locale(): void
    {
        $this->withSession(['locale' => 'ru'])
            ->get('/locale/de')
            ->assertNotFound();

        $this->assertSame('ru', session('locale'));
    }

    public function test_switching_locale_preserves_query_string_and_persists_across_browser_restart(): void
    {
        $switchResponse = $this
            ->from('/players?search=ivan')
            ->get('/locale/ru');

        $switchResponse->assertRedirect('/players?search=ivan');
        $switchResponse->assertSessionHas('locale', 'ru');
        $switchResponse->assertCookie('locale', 'ru');

        $cookie = collect($switchResponse->headers->getCookies())
            ->first(fn ($c) => $c->getName() === 'locale');

        $this->assertNotNull($cookie);
        $this->assertSame('lax', strtolower((string) $cookie->getSameSite()));
        $this->assertGreaterThan(now()->addDays(360)->getTimestamp(), $cookie->getExpiresTime());

        // Simulate browser restart: empty session, only the persistent cookie remains.
        $this->flushSession();

        $afterRestart = $this->withCookie('locale', 'ru')->get('/players?search=ivan');
        $afterRestart->assertOk();
        $afterRestart->assertSee('<html lang="ru">', false);
        $afterRestart->assertSee(__('players.index.title', [], 'ru'));
    }

    public function test_switching_locale_without_referer_redirects_guest_to_home_and_user_to_dashboard(): void
    {
        $this->get('/locale/ru')->assertRedirect(url('/'));

        $user = User::factory()->create();
        $this->actingAs($user)
            ->get('/locale/en')
            ->assertRedirect(route('dashboard'));
    }

    public function test_language_switcher_is_rendered_for_guests_and_authenticated_users(): void
    {
        $guestLogin = $this->get('/login');
        $guestLogin->assertOk();
        $guestLogin->assertSee('aria-current="true"', false);
        $guestLogin->assertSee('hreflang="ru"', false);
        $guestLogin->assertSee(route('locale.update', 'ru'), false);

        $user = User::factory()->create();
        $dashboard = $this->actingAs($user)->withSession(['locale' => 'ru'])->get('/dashboard');
        $dashboard->assertOk();
        $dashboard->assertSee('<html lang="ru">', false);
        $dashboard->assertSee('hreflang="en"', false);
        $dashboard->assertSee(route('locale.update', 'en'), false);
    }
}
