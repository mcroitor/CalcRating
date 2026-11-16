<?php

namespace Database\Factories;

use App\Models\Player;
use App\Models\Tournament;
use App\Models\TournamentParticipant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TournamentParticipant>
 */
class TournamentParticipantFactory extends Factory
{
    protected $model = TournamentParticipant::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tournament_id' => Tournament::factory(),
            'player_id' => Player::factory(),
            'points' => 0,
            'rating_before' => 1000,
            'rating_after' => null,
            'version' => 1,
        ];
    }
}
