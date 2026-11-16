<?php

namespace Tests\Feature\Localization;

use App\Enums\ChessTitle;
use App\Enums\PairingType;
use App\Enums\RatingMode;
use App\Enums\TournamentStatus;
use App\Models\Club;
use App\Models\Player;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

class LocalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_en_and_ru_translation_files_have_matching_keys_and_no_bom(): void
    {
        $enFiles = collect(File::files(lang_path('en')))
            ->map(fn ($f) => $f->getFilename())
            ->sort()
            ->values()
            ->all();

        $ruFiles = collect(File::files(lang_path('ru')))
            ->map(fn ($f) => $f->getFilename())
            ->sort()
            ->values()
            ->all();

        $this->assertSame($enFiles, $ruFiles, 'Group files in lang/en and lang/ru must match.');

        foreach (['en', 'ru'] as $locale) {
            foreach (File::files(lang_path($locale)) as $file) {
                $raw = file_get_contents($file->getPathname());
                $this->assertStringStartsNotWith("\xEF\xBB\xBF", $raw, "BOM found in {$file->getPathname()}");
            }
            $jsonRaw = file_get_contents(lang_path("{$locale}.json"));
            $this->assertStringStartsNotWith("\xEF\xBB\xBF", $jsonRaw, "BOM found in lang/{$locale}.json");
        }

        foreach ($enFiles as $filename) {
            if ($filename === 'validation.php') {
                continue;
            }

            $enKeys = array_keys(Arr::dot(require lang_path("en/{$filename}")));
            $ruKeys = array_keys(Arr::dot(require lang_path("ru/{$filename}")));

            sort($enKeys);
            sort($ruKeys);

            $this->assertSame($enKeys, $ruKeys, "Translation key mismatch in {$filename}");
        }

        $enJson = json_decode(file_get_contents(lang_path('en.json')), true, 512, JSON_THROW_ON_ERROR);
        $ruJson = json_decode(file_get_contents(lang_path('ru.json')), true, 512, JSON_THROW_ON_ERROR);

        $enJsonKeys = array_keys($enJson);
        $ruJsonKeys = array_keys($ruJson);
        sort($enJsonKeys);
        sort($ruJsonKeys);

        $this->assertSame($enJsonKeys, $ruJsonKeys, 'Key mismatch between lang/en.json and lang/ru.json');

        $enValidation = require lang_path('en/validation.php');
        $ruValidation = require lang_path('ru/validation.php');

        $requiredRules = [
            'required', 'string', 'integer', 'numeric', 'boolean', 'array',
            'email', 'date', 'date_format', 'after_or_equal', 'before_or_equal',
            'unique', 'exists', 'in', 'max', 'min', 'between', 'size',
            'confirmed', 'current_password', 'password', 'image', 'mimes',
            'file', 'regex',
        ];

        foreach ($requiredRules as $rule) {
            $this->assertArrayHasKey($rule, $enValidation, "Missing rule '{$rule}' in en/validation.php");
            $this->assertArrayHasKey($rule, $ruValidation, "Missing rule '{$rule}' in ru/validation.php");
        }

        $enAttrs = array_keys($enValidation['attributes'] ?? []);
        $ruAttrs = array_keys($ruValidation['attributes'] ?? []);
        sort($enAttrs);
        sort($ruAttrs);

        $this->assertSame($enAttrs, $ruAttrs, 'Validation attribute keys must match between en and ru.');
    }

    public function test_app_and_views_contain_no_cyrillic_literals(): void
    {
        $offenders = [];

        foreach ([app_path(), resource_path('views')] as $directory) {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));
            foreach ($iterator as $file) {
                if (! $file->isFile() || $file->getExtension() !== 'php') {
                    continue;
                }

                $contents = file_get_contents($file->getPathname());
                if (preg_match('/[А-Яа-яЁё]/u', $contents)) {
                    $offenders[] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file->getPathname());
                }
            }
        }

        $this->assertSame([], $offenders, 'Cyrillic literals found in: '.implode(', ', $offenders));
    }

    public function test_missing_russian_translation_falls_back_to_english(): void
    {
        app('translator')->addLines([
            'common.only_in_english_probe' => 'English fallback text',
        ], 'en');

        app()->setLocale('ru');

        $this->assertSame('English fallback text', __('common.only_in_english_probe'));
    }

    public function test_pluralization_works_for_english_and_russian(): void
    {
        $this->assertSame('1 year', trans_choice('common.years_old', 1, ['age' => 1], 'en'));
        $this->assertSame('5 years', trans_choice('common.years_old', 5, ['age' => 5], 'en'));

        $this->assertSame('1 год', trans_choice('common.years_old', 1, ['age' => 1], 'ru'));
        $this->assertSame('2 года', trans_choice('common.years_old', 2, ['age' => 2], 'ru'));
        $this->assertSame('5 лет', trans_choice('common.years_old', 5, ['age' => 5], 'ru'));
        $this->assertSame('21 год', trans_choice('common.years_old', 21, ['age' => 21], 'ru'));
    }

    public function test_new_locale_can_be_added_via_config_and_translations_without_code_changes(): void
    {
        config([
            'locales.supported' => ['en', 'ru', 'es'],
            'locales.names.es' => 'Español',
            'locales.date_format.es' => 'd/m/Y',
        ]);

        Route::getRoutes()->getByName('locale.update')?->whereIn('locale', config('locales.supported'));

        app('translator')->addLines([
            'players.index.title' => 'Registro de jugadores',
            'nav.players' => 'Jugadores',
        ], 'es');

        $switch = $this->from('/players')->get('/locale/es');
        $switch->assertRedirect('/players');
        $switch->assertCookie('locale', 'es');

        $page = $this->withSession(['locale' => 'es'])->get('/players');
        $page->assertOk();
        $page->assertSee('<html lang="es">', false);
        $page->assertSee('Registro de jugadores');
        $page->assertSee('Jugadores');
        // Untranslated Spanish keys fall back to English seamlessly.
        $page->assertSee(__('common.reset', [], 'en'));
        $page->assertSee('ES');
    }

    public function test_all_primary_screens_render_in_both_locales_without_raw_keys(): void
    {
        $admin = User::factory()->admin()->create();
        $targetUser = User::factory()->arbiter()->create();
        $player = Player::factory()->create([
            'first_name' => 'Garry',
            'last_name' => 'Kasparov',
            'birth_date' => '1963-04-13',
        ]);
        $club = Club::factory()->create();
        $tournament = Tournament::factory()->create(['club_id' => $club->id, 'arbiter_id' => $admin->id]);

        $guestUrls = ['/', '/login', '/register', '/forgot-password', '/clubs', "/clubs/{$club->id}", '/players', "/players/{$player->id}", '/tournaments', "/tournaments/{$tournament->id}"];
        $adminUrls = [
            '/dashboard',
            '/profile',
            '/clubs/create',
            "/clubs/{$club->id}/edit",
            '/players/create',
            "/players/{$player->id}/edit",
            '/players/import',
            '/tournaments/create',
            "/tournaments/{$tournament->id}/edit",
            '/admin/users',
            '/admin/users/create',
            "/admin/users/{$targetUser->id}/edit",
        ];

        $rawKeyPattern = '/\b(?:common|nav|auth|profile|clubs|players|tournaments|pairings|admin|errors|exports|enums|welcome|dashboard)\.[a-z0-9_]+(?:\.[a-z0-9_]+)+\b/';

        foreach (['en', 'ru'] as $locale) {
            auth()->forgetGuards();

            foreach ($guestUrls as $url) {
                $this->flushSession();
                $response = $this->withCookie('locale', $locale)->get($url);
                $response->assertOk();
                $response->assertSee("<html lang=\"{$locale}\">", false);
                $this->assertDoesNotMatchRegularExpression($rawKeyPattern, $response->getContent(), "Raw translation key leaked on {$url} ({$locale})");
            }

            foreach ($adminUrls as $url) {
                $response = $this->actingAs($admin)->withSession(['locale' => $locale])->get($url);
                $response->assertOk();
                $response->assertSee("<html lang=\"{$locale}\">", false);
                $this->assertDoesNotMatchRegularExpression($rawKeyPattern, $response->getContent(), "Raw translation key leaked on {$url} ({$locale})");
            }
        }
    }

    public function test_validation_errors_are_localized_in_en_and_ru(): void
    {
        $arbiter = User::factory()->arbiter()->create();

        $enResponse = $this->actingAs($arbiter)
            ->withSession(['locale' => 'en'])
            ->from('/players/create')
            ->post('/players', []);

        $enResponse->assertRedirect('/players/create');
        $enResponse->assertSessionHasErrors('first_name');
        $this->assertSame(
            __('validation.required', ['attribute' => __('validation.attributes.first_name', [], 'en')], 'en'),
            session('errors')->first('first_name')
        );

        $ruResponse = $this->actingAs($arbiter)
            ->withSession(['locale' => 'ru'])
            ->from('/players/create')
            ->post('/players', []);

        $ruResponse->assertRedirect('/players/create');
        $ruResponse->assertSessionHasErrors('first_name');
        $this->assertSame(
            __('validation.required', ['attribute' => __('validation.attributes.first_name', [], 'ru')], 'ru'),
            session('errors')->first('first_name')
        );
    }

    public function test_409_conflict_renders_localized_html_and_json_messages(): void
    {
        $arbiter = User::factory()->arbiter()->create();
        $player = Player::factory()->create(['version' => 1]);
        $player->updateWithVersion(['notes' => 'Bumped'], 1);

        $stalePayload = [
            'first_name' => $player->first_name,
            'last_name' => $player->last_name,
            'birth_date' => $player->birth_date->format('Y-m-d'),
            'title' => $player->title->value,
            'notes' => 'Stale',
            'version' => 1,
        ];

        foreach (['en', 'ru'] as $locale) {
            $html = $this->actingAs($arbiter)
                ->withSession(['locale' => $locale])
                ->put(route('players.update', $player), $stalePayload);

            $html->assertStatus(409);
            $html->assertSee("<html lang=\"{$locale}\">", false);
            $html->assertSee(__('errors.409.heading', [], $locale));
            $html->assertSee(__('errors.409.message', [], $locale));

            $json = $this->actingAs($arbiter)
                ->withSession(['locale' => $locale])
                ->putJson(route('players.update', $player), $stalePayload);

            $json->assertStatus(409);
            $json->assertExactJson([
                'message' => __('errors.409.message', [], $locale),
                'code' => 'CONCURRENT_MODIFICATION',
            ]);
        }
    }

    public function test_password_reset_and_email_verification_notifications_use_request_locale(): void
    {
        Notification::fake();

        $user = User::factory()->unverified()->create();

        $this->withSession(['locale' => 'ru'])
            ->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
            $this->assertSame('ru', $notification->locale);

            app()->setLocale('ru');
            $mail = $notification->toMail($user);
            $this->assertSame(__('Reset Password Notification', [], 'ru'), $mail->subject);
            $this->assertStringContainsString(
                __('You are receiving this email because we received a password reset request for your account.', [], 'ru'),
                implode(' ', $mail->introLines)
            );

            return true;
        });

        Notification::fake();

        $this->actingAs($user)
            ->withSession(['locale' => 'ru'])
            ->post(route('verification.send'));

        Notification::assertSentTo($user, VerifyEmail::class, function (VerifyEmail $notification) use ($user) {
            $this->assertSame('ru', $notification->locale);

            app()->setLocale('ru');
            $mail = $notification->toMail($user);
            $this->assertSame(__('Verify Email Address', [], 'ru'), $mail->subject);

            return true;
        });
    }

    public function test_exports_localize_html_and_pdf_while_keeping_xlsx_headers_latin(): void
    {
        $arbiter = User::factory()->arbiter()->create();
        $player = Player::factory()->create([
            'first_name' => 'Anatoly',
            'last_name' => 'Karpov',
            'birth_date' => '1951-05-23',
            'title' => ChessTitle::GM,
            'current_rating' => 2500,
        ]);

        $tournament = Tournament::factory()->create([
            'arbiter_id' => $arbiter->id,
            'pairing_type' => PairingType::RoundRobin,
            'rating_mode' => RatingMode::Average,
            'status' => TournamentStatus::InProgress,
            'start_date' => '2026-03-01',
            'end_date' => '2026-03-03',
        ]);
        $tournament->participants()->create([
            'player_id' => $player->id,
            'points' => 1.0,
            'rating_before' => 2500,
            'version' => 1,
        ]);

        // HTML exports switch language and date formatting dynamically.
        $enHtml = $this->actingAs($arbiter)
            ->withSession(['locale' => 'en'])
            ->get(route('tournaments.export', [$tournament, 'html']));
        $enHtml->assertOk();
        $enHtml->assertSee('<html lang="en">', false);
        $enHtml->assertSee(__('tournaments.export.standings', [], 'en'));
        $enHtml->assertSee('Mar 1, 2026');

        $ruHtml = $this->actingAs($arbiter)
            ->withSession(['locale' => 'ru'])
            ->get(route('tournaments.export', [$tournament, 'html']));
        $ruHtml->assertOk();
        $ruHtml->assertSee('<html lang="ru">', false);
        $ruHtml->assertSee(__('tournaments.export.standings', [], 'ru'));
        $ruHtml->assertSee('01.03.2026');

        $ruPdf = $this->actingAs($arbiter)
            ->withSession(['locale' => 'ru'])
            ->get(route('players.export', 'pdf'));
        $ruPdf->assertOk();
        $ruPdf->assertHeader('content-type', 'application/pdf');

        // XLSX headers stay Latin even when exported in Russian locale.
        $playersXlsx = $this->actingAs($arbiter)
            ->withSession(['locale' => 'ru'])
            ->get(route('players.export', 'xlsx'));
        $playersSheet = $this->readXlsx($playersXlsx->baseResponse);
        $this->assertSame(
            ['id', 'last_name', 'first_name', 'birth_date', 'age', 'title', 'current_rating'],
            $playersSheet->getSheet(0)->toArray()[0]
        );

        $tournamentXlsx = $this->actingAs($arbiter)
            ->withSession(['locale' => 'ru'])
            ->get(route('tournaments.export', [$tournament, 'xlsx']));
        $tournamentSheet = $this->readXlsx($tournamentXlsx->baseResponse);
        $rows = $tournamentSheet->getSheet(0)->toArray();
        $this->assertContains(
            ['place', 'player', 'title', 'rating_before', 'points', 'rating_after', 'delta'],
            $rows
        );
    }

    private function readXlsx(mixed $baseResponse): Spreadsheet
    {
        $this->assertInstanceOf(StreamedResponse::class, $baseResponse);

        ob_start();
        $baseResponse->sendContent();
        $binary = ob_get_clean();

        $tmp = tempnam(sys_get_temp_dir(), 'xlsx_i18n_');
        file_put_contents($tmp, $binary);

        try {
            return IOFactory::load($tmp);
        } finally {
            @unlink($tmp);
        }
    }
}
