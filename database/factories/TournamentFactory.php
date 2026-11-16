<?php

namespace Database\Factories;

use App\Enums\ClubStaffRole;
use App\Enums\PairingType;
use App\Enums\RatingMode;
use App\Enums\TournamentStatus;
use App\Models\Club;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tournament>
 */
class TournamentFactory extends Factory
{
    protected $model = Tournament::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startDate = now()->addDays(7);

        return [
            'club_id' => Club::factory(),
            'arbiter_id' => User::factory()->arbiter(),
            'name' => 'Шахматный турнир №'.fake()->unique()->numberBetween(1, 9999),
            'pairing_type' => PairingType::Swiss,
            'rating_mode' => RatingMode::Average,
            'rounds_count' => 5,
            'games_per_round' => 1,
            'start_date' => $startDate->toDateString(),
            'end_date' => $startDate->copy()->addDays(2)->toDateString(),
            'location' => 'Шахматный клуб',
            'notes' => null,
            'status' => TournamentStatus::Draft,
            'version' => 1,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Tournament $tournament): void {
            if ($tournament->club_id && $tournament->arbiter_id && ! $tournament->club->hasStaffUser($tournament->arbiter_id)) {
                $tournament->club->staff()->attach($tournament->arbiter_id, [
                    'role' => ClubStaffRole::Manager->value,
                ]);
            }
        });
    }

    public function inProgress(): static
    {
        return $this->state(fn () => [
            'status' => TournamentStatus::InProgress,
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => TournamentStatus::Completed,
        ]);
    }
}
