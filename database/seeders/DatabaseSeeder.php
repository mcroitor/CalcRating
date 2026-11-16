<?php

namespace Database\Seeders;

use App\Enums\ChessTitle;
use App\Enums\ClubStaffRole;
use App\Enums\ClubStatus;
use App\Enums\PairingType;
use App\Enums\RatingMode;
use App\Enums\TournamentStatus;
use App\Enums\UserRole;
use App\Models\Club;
use App\Models\Player;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new \RuntimeException(
                'DatabaseSeeder contains demo credentials and cannot run in production. Use "php artisan admin:bootstrap" instead.'
            );
        }

        User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Главный',
                'surname' => 'Администратор',
                'password' => Hash::make('password'),
                'role' => UserRole::Admin,
                'version' => 1,
            ]
        );

        User::firstOrCreate(
            ['email' => 'arbiter@example.com'],
            [
                'name' => 'Главный',
                'surname' => 'Судья',
                'password' => Hash::make('password'),
                'role' => UserRole::Arbiter,
                'version' => 1,
            ]
        );

        User::firstOrCreate(
            ['email' => 'guest@example.com'],
            [
                'name' => 'Тестовый',
                'surname' => 'Гость',
                'password' => Hash::make('password'),
                'role' => UserRole::Guest,
                'version' => 1,
            ]
        );

        // Seed initial chess players
        $samplePlayers = [
            [
                'first_name' => 'Гарри',
                'last_name' => 'Каспаров',
                'birth_date' => '1963-04-13',
                'title' => ChessTitle::GM,
                'current_rating' => 2800,
                'notes' => '13-й чемпион мира по шахматам',
            ],
            [
                'first_name' => 'Анатолий',
                'last_name' => 'Карпов',
                'birth_date' => '1951-05-23',
                'title' => ChessTitle::GM,
                'current_rating' => 2730,
                'notes' => '12-й чемпион мира по шахматам',
            ],
            [
                'first_name' => 'Магнус',
                'last_name' => 'Карлсен',
                'birth_date' => '1990-11-30',
                'title' => ChessTitle::GM,
                'current_rating' => 2850,
                'notes' => '16-й чемпион мира по шахматам',
            ],
            [
                'first_name' => 'Юдит',
                'last_name' => 'Полгар',
                'birth_date' => '1976-07-23',
                'title' => ChessTitle::GM,
                'current_rating' => 2675,
                'notes' => 'Сильнейшая шахматистка в истории',
            ],
            [
                'first_name' => 'Михаил',
                'last_name' => 'Таль',
                'birth_date' => '1936-11-09',
                'title' => ChessTitle::GM,
                'current_rating' => 2705,
                'notes' => '8-й чемпион мира по шахматам, "волшебник из Риги"',
            ],
        ];

        foreach ($samplePlayers as $playerData) {
            Player::firstOrCreate(
                [
                    'first_name' => $playerData['first_name'],
                    'last_name' => $playerData['last_name'],
                    'birth_date' => $playerData['birth_date'],
                ],
                $playerData
            );
        }

        // Seed a demo club and draft tournament with participants.
        $admin = User::where('email', 'admin@example.com')->first();
        $arbiter = User::where('email', 'arbiter@example.com')->first();

        $club = Club::firstOrCreate(
            ['name' => 'Шахматный клуб CalcRating'],
            [
                'code' => 'CRCLUB',
                'city' => 'Кишинёв',
                'address' => 'ул. Шахматная, 1',
                'website' => 'https://example.com',
                'description' => 'Демонстрационный шахматный клуб по умолчанию.',
                'status' => ClubStatus::Active,
                'version' => 1,
            ]
        );

        if ($admin) {
            $club->staff()->syncWithoutDetaching([
                $admin->id => ['role' => ClubStaffRole::Manager->value],
            ]);
        }

        if ($arbiter) {
            $club->staff()->syncWithoutDetaching([
                $arbiter->id => ['role' => ClubStaffRole::Arbiter->value],
            ]);
        }

        $clubPlayers = Player::query()
            ->whereIn('last_name', ['Каспаров', 'Карпов', 'Карлсен', 'Полгар'])
            ->get();

        foreach ($clubPlayers as $clubPlayer) {
            $club->members()->syncWithoutDetaching([
                $clubPlayer->id => ['joined_at' => now()->toDateString()],
            ]);
        }

        if ($arbiter) {
            $tournament = Tournament::firstOrCreate(
                ['name' => 'Клубный турнир «Осень»'],
                [
                    'club_id' => $club->id,
                    'arbiter_id' => $arbiter->id,
                    'pairing_type' => PairingType::Swiss,
                    'rating_mode' => RatingMode::Average,
                    'rounds_count' => 5,
                    'games_per_round' => 1,
                    'start_date' => now()->addDays(7)->toDateString(),
                    'end_date' => now()->addDays(9)->toDateString(),
                    'location' => 'Шахматный клуб CalcRating',
                    'notes' => 'Демонстрационный турнир, состав можно изменить до старта.',
                    'status' => TournamentStatus::Draft,
                    'version' => 1,
                ]
            );

            Player::query()
                ->whereIn('last_name', ['Каспаров', 'Карпов', 'Карлсен', 'Полгар'])
                ->get()
                ->each(function (Player $player) use ($tournament) {
                    $tournament->participants()->firstOrCreate(
                        ['player_id' => $player->id],
                        [
                            'points' => 0,
                            'rating_before' => $player->current_rating,
                            'version' => 1,
                        ]
                    );
                });
        }
    }
}
