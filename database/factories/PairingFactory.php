<?php

namespace Database\Factories;

use App\Enums\PairingResult;
use App\Models\Pairing;
use App\Models\Player;
use App\Models\Tournament;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Pairing>
 */
class PairingFactory extends Factory
{
    protected $model = Pairing::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tournament_id' => Tournament::factory(),
            'round_number' => 1,
            'board_number' => 1,
            'white_player_id' => Player::factory(),
            'black_player_id' => Player::factory(),
            'result' => PairingResult::Pending,
            'advanced_player_id' => null,
            'version' => 1,
        ];
    }
}
