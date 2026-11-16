<?php

namespace Tests\Feature;

use App\Models\Player;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class PlayerImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_import(): void
    {
        $this->get('/players/import')->assertRedirect(route('login'));
        $this->post('/players/import', [])->assertRedirect(route('login'));
    }

    public function test_arbiter_can_import_valid_csv(): void
    {
        $arbiter = User::factory()->arbiter()->create();

        $csvContent = "first_name,last_name,birth_date,current_rating,title,notes\n"
            ."Хосе Рауль,Капабланка,1888-11-19,2725,GM,3-й чемпион мира\n"
            ."Эмануил,Ласкер,1868-12-24,2720,GM,2-й чемпион мира\n";

        $file = UploadedFile::fake()->createWithContent('players.csv', $csvContent);

        $response = $this->actingAs($arbiter)->post('/players/import', [
            'file' => $file,
        ]);

        $response->assertRedirect(route('players.import'));
        $response->assertSessionHas('import_result');

        $result = session('import_result');
        $this->assertSame(2, $result['imported']);
        $this->assertSame(0, $result['errors_count']);

        $this->assertDatabaseHas('players', [
            'last_name' => 'Капабланка',
            'first_name' => 'Хосе Рауль',
            'current_rating' => 2725,
        ]);

        $this->assertDatabaseHas('players', [
            'last_name' => 'Ласкер',
            'first_name' => 'Эмануил',
            'current_rating' => 2720,
        ]);

        // Each player got an initial rating record
        $capa = Player::where('last_name', 'Капабланка')->first();
        $this->assertDatabaseHas('ratings', [
            'player_id' => $capa->id,
            'value' => 2725,
            'tournament_id' => null,
        ]);
    }

    public function test_deduplication_skips_existing_players(): void
    {
        $arbiter = User::factory()->arbiter()->create();

        // Existing player in database
        Player::factory()->create([
            'first_name' => 'Вильгельм',
            'last_name' => 'Стейниц',
            'birth_date' => '1836-05-17',
            'current_rating' => 2600,
        ]);

        $csvContent = "first_name,last_name,birth_date,current_rating,title\n"
            ."Вильгельм,Стейниц,1836-05-17,2900,GM\n" // duplicate, should be skipped without updating rating
            ."Макс,Эйве,1901-05-20,2650,GM\n";

        $file = UploadedFile::fake()->createWithContent('players.csv', $csvContent);

        $response = $this->actingAs($arbiter)->post('/players/import', [
            'file' => $file,
        ]);

        $response->assertRedirect(route('players.import'));

        $result = session('import_result');
        $this->assertSame(1, $result['imported']);
        $this->assertCount(1, $result['warnings']);
        $this->assertStringContainsString('Стейниц', $result['warnings'][0]['message']);

        // Existing player rating is unchanged
        $steinitz = Player::where('last_name', 'Стейниц')->first();
        $this->assertSame(2600, $steinitz->current_rating);
    }

    public function test_partial_success_with_invalid_rows(): void
    {
        $arbiter = User::factory()->arbiter()->create();

        $csvContent = "first_name,last_name,birth_date,current_rating,title\n"
            ."Тигран,Петросян,1929-06-17,2640,GM\n" // valid
            ."Битый,,1930-01-01,1500,None\n" // missing last_name
            .",Битый2,1930-01-01,1500,None\n" // missing first_name
            ."Борис,Спасский,invalid-date,2690,GM\n" // invalid date
            ."Будущий,Человек,2099-01-01,1500,None\n"; // future date

        $file = UploadedFile::fake()->createWithContent('players.csv', $csvContent);

        $response = $this->actingAs($arbiter)->post('/players/import', [
            'file' => $file,
        ]);

        $response->assertRedirect(route('players.import'));

        $result = session('import_result');
        $this->assertSame(1, $result['imported']); // only Петросян
        $this->assertSame(4, $result['errors_count']);

        $this->assertDatabaseHas('players', [
            'last_name' => 'Петросян',
            'first_name' => 'Тигран',
        ]);
    }

    public function test_missing_required_headers_displays_error(): void
    {
        $arbiter = User::factory()->arbiter()->create();

        $csvContent = "name,rating\nИван,1500\n";
        $file = UploadedFile::fake()->createWithContent('bad_headers.csv', $csvContent);

        $response = $this->actingAs($arbiter)->post('/players/import', [
            'file' => $file,
        ]);

        $response->assertRedirect(route('players.import'));

        $result = session('import_result');
        $this->assertSame(0, $result['imported']);
        $this->assertStringContainsString(
            __('players.import.missing_columns', ['columns' => 'first_name, last_name, birth_date']),
            $result['errors'][0]['message']
        );
    }
}
