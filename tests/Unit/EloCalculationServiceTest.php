<?php

namespace Tests\Unit;

use App\Enums\RatingMode;
use App\Services\EloCalculationService;
use Tests\TestCase;

class EloCalculationServiceTest extends TestCase
{
    private EloCalculationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new EloCalculationService;
    }

    public function test_k_factor_is_40_for_fewer_than_30_games(): void
    {
        $this->assertSame(40, $this->service->kFactor(29, 30, 2500));
    }

    public function test_k_factor_is_40_for_junior_below_2300(): void
    {
        $this->assertSame(40, $this->service->kFactor(100, 17, 2299));
    }

    public function test_k_factor_is_20_for_junior_at_2300(): void
    {
        $this->assertSame(20, $this->service->kFactor(100, 17, 2300));
    }

    public function test_k_factor_is_10_for_adult_at_2400(): void
    {
        $this->assertSame(10, $this->service->kFactor(100, 25, 2400));
    }

    public function test_k_factor_is_20_for_adult_at_2399(): void
    {
        $this->assertSame(20, $this->service->kFactor(100, 25, 2399));
    }

    public function test_k_factor_does_not_treat_unknown_age_as_junior(): void
    {
        $this->assertSame(20, $this->service->kFactor(100, null, 2200));
    }

    public function test_few_games_rule_beats_high_rating(): void
    {
        $this->assertSame(40, $this->service->kFactor(5, 30, 2600));
    }

    public function test_average_mode_calculates_expected_rating(): void
    {
        $games = [
            ['opponent_rating' => 1800, 'score' => 1.0],
            ['opponent_rating' => 2200, 'score' => 0.5],
        ];

        // opponents average 2000 = Rbefore; two games → E = 1.0; S = 1.5; K=20 → 2010
        $this->assertSame(2010, $this->service->calculate(2000, $games, 20, RatingMode::Average));
    }

    public function test_per_game_mode_can_differ_from_average_mode(): void
    {
        $games = [
            ['opponent_rating' => 2600, 'score' => 0.0],
            ['opponent_rating' => 1600, 'score' => 1.0],
        ];

        $this->assertSame(2006, $this->service->calculate(2000, $games, 20, RatingMode::Average));
        $this->assertSame(2001, $this->service->calculate(2000, $games, 20, RatingMode::PerGame));
    }

    public function test_empty_games_do_not_change_rating(): void
    {
        $this->assertSame(1800, $this->service->calculate(1800, [], 20, RatingMode::Average));
        $this->assertSame(1800, $this->service->calculate(1800, [], 40, RatingMode::PerGame));
    }

    public function test_score_sums_wins_and_draws(): void
    {
        $games = [
            ['opponent_rating' => 1800, 'score' => 1.0],
            ['opponent_rating' => 2200, 'score' => 0.5],
            ['opponent_rating' => 2000, 'score' => 0.0],
        ];

        $this->assertEqualsWithDelta(1.5, $this->service->score($games), 0.0001);
    }

    public function test_expected_exposes_average_and_per_game(): void
    {
        $games = [
            ['opponent_rating' => 2600, 'score' => 0.0],
            ['opponent_rating' => 1600, 'score' => 1.0],
        ];

        $this->assertEqualsWithDelta(0.719848, $this->service->expected(2000, $games, RatingMode::Average), 0.0001);
        $this->assertEqualsWithDelta(0.939744, $this->service->expected(2000, $games, RatingMode::PerGame), 0.0001);
    }
}
