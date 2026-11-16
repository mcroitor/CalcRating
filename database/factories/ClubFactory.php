<?php

namespace Database\Factories;

use App\Enums\ClubStatus;
use App\Models\Club;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Club>
 */
class ClubFactory extends Factory
{
    protected $model = Club::class;

    public function definition(): array
    {
        return [
            'name' => 'Chess Club '.fake()->unique()->company(),
            'code' => strtoupper(fake()->unique()->lexify('CC???')),
            'city' => fake()->city(),
            'address' => fake()->streetAddress(),
            'website' => 'https://example.com',
            'description' => fake()->sentence(),
            'logo_path' => null,
            'status' => ClubStatus::Active,
            'version' => 1,
        ];
    }

    public function archived(): static
    {
        return $this->state(fn () => [
            'status' => ClubStatus::Archived,
        ]);
    }
}
