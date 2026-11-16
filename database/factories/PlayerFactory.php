<?php

namespace Database\Factories;

use App\Enums\ChessTitle;
use App\Models\Player;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Player>
 */
class PlayerFactory extends Factory
{
    protected $model = Player::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'birth_date' => fake()->dateTimeBetween('-50 years', '-10 years')->format('Y-m-d'),
            'title' => ChessTitle::None,
            'current_rating' => fake()->numberBetween(1000, 2400),
            'notes' => null,
            'version' => 1,
        ];
    }
}
