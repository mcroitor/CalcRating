<?php

namespace App\Services;

use App\Enums\PairingResult;
use App\Enums\RatingMode;
use App\Models\Pairing;
use App\Models\Player;
use App\Models\Tournament;
use App\Models\TournamentParticipant;
use App\Support\RatingResult;
use Illuminate\Support\Collection;

class EloCalculationService
{
    public const GAMES_FOR_STANDARD_K = 30;

    public const JUNIOR_AGE = 18;

    public const JUNIOR_MAX_RATING = 2300;

    public const HIGH_RATING = 2400;

    public const K_NEW_PLAYER = 40;

    public const K_STANDARD = 20;

    public const K_HIGH_RATING = 10;

    /**
     * Determine the K factor using the agreed rule precedence:
     * fewer than 30 games → junior → high rating → standard.
     */
    public function kFactor(int $gamesPlayed, ?int $age, int $ratingBefore): int
    {
        if ($gamesPlayed < self::GAMES_FOR_STANDARD_K) {
            return self::K_NEW_PLAYER;
        }

        if ($age !== null && $age < self::JUNIOR_AGE && $ratingBefore < self::JUNIOR_MAX_RATING) {
            return self::K_NEW_PLAYER;
        }

        if ($ratingBefore >= self::HIGH_RATING) {
            return self::K_HIGH_RATING;
        }

        return self::K_STANDARD;
    }

    /**
     * Calculate the new rating for one player.
     *
     * Each game is an array with the opponent's starting rating and the score
     * earned by the player (1 win, 0.5 draw, 0 loss). Byes, pending games and
     * double forfeits must be filtered out by the caller.
     *
     * @param  list<array{opponent_rating: int, score: float}>  $games
     */
    public function calculate(int $ratingBefore, array $games, int $kFactor, RatingMode $mode): int
    {
        if ($games === []) {
            return $ratingBefore;
        }

        $score = $this->score($games);
        $expected = $mode === RatingMode::Average
            ? $this->expectedAverage($ratingBefore, $games)
            : $this->expectedPerGame($ratingBefore, $games);

        return (int) round($ratingBefore + $kFactor * ($score - $expected));
    }

    /**
     * Calculate rating changes for every participant of the tournament.
     *
     * @return Collection<int, RatingResult> Keyed by participant ID.
     */
    public function calculateForTournament(Tournament $tournament): Collection
    {
        $participants = $tournament->participants()->with('player')->get();

        if ($participants->isEmpty()) {
            return collect();
        }

        $ratingsBefore = $participants
            ->mapWithKeys(fn (TournamentParticipant $participant) => [
                $participant->player_id => $participant->rating_before,
            ])
            ->all();

        $gamesByPlayer = [];

        foreach ($tournament->pairings()->get() as $pairing) {
            if ($this->isRatingGame($pairing)) {
                $gamesByPlayer[$pairing->white_player_id][] = [
                    'opponent_rating' => $ratingsBefore[$pairing->black_player_id],
                    'score' => $pairing->result->whitePoints(),
                ];
                $gamesByPlayer[$pairing->black_player_id][] = [
                    'opponent_rating' => $ratingsBefore[$pairing->white_player_id],
                    'score' => $pairing->result->blackPoints(),
                ];
            }
        }

        $gamesPlayed = $this->completedGamesCount(
            $participants->pluck('player_id')->all()
        );

        return $participants->mapWithKeys(function (TournamentParticipant $participant) use (
            $tournament,
            $gamesByPlayer,
            $gamesPlayed
        ) {
            $games = $gamesByPlayer[$participant->player_id] ?? [];
            $kFactor = $this->kFactor(
                $gamesPlayed[$participant->player_id] ?? 0,
                $this->ageAtTournament($participant->player, $tournament),
                $participant->rating_before
            );

            return [$participant->id => new RatingResult(
                participantId: $participant->id,
                playerId: $participant->player_id,
                ratingBefore: $participant->rating_before,
                ratingAfter: $this->calculate(
                    $participant->rating_before,
                    $games,
                    $kFactor,
                    $tournament->rating_mode
                ),
                games: count($games),
                score: $this->score($games),
                expected: $this->expected($participant->rating_before, $games, $tournament->rating_mode),
                kFactor: $kFactor,
            )];
        });
    }

    /**
     * Completed rated games per player across every tournament in the system.
     * Byes, pending games and double forfeits are excluded; outcomes are read
     * from pairings, not from rating history rows.
     *
     * @param  list<int>  $playerIds
     * @return array<int, int>
     */
    public function completedGamesCount(array $playerIds): array
    {
        $counts = array_fill_keys($playerIds, 0);

        if ($playerIds === []) {
            return $counts;
        }

        $pairings = Pairing::query()
            ->whereNotNull('black_player_id')
            ->whereNotIn('result', [
                PairingResult::Pending->value,
                PairingResult::DoubleForfeit->value,
            ])
            ->where(function ($query) use ($playerIds) {
                $query->whereIn('white_player_id', $playerIds)
                    ->orWhereIn('black_player_id', $playerIds);
            })
            ->get(['white_player_id', 'black_player_id']);

        foreach ($pairings as $pairing) {
            if (array_key_exists($pairing->white_player_id, $counts)) {
                $counts[$pairing->white_player_id]++;
            }

            if (array_key_exists($pairing->black_player_id, $counts)) {
                $counts[$pairing->black_player_id]++;
            }
        }

        return $counts;
    }

    /**
     * Age of the player on the tournament start date.
     */
    public function ageAtTournament(Player $player, Tournament $tournament): ?int
    {
        if ($player->birth_date === null || $tournament->start_date === null) {
            return null;
        }

        return (int) $player->birth_date->copy()->startOfDay()->diffInYears(
            $tournament->start_date->copy()->startOfDay()
        );
    }

    /**
     * @param  list<array{opponent_rating: int, score: float}>  $games
     */
    public function expected(int $ratingBefore, array $games, RatingMode $mode): float
    {
        if ($games === []) {
            return 0.0;
        }

        return $mode === RatingMode::Average
            ? $this->expectedAverage($ratingBefore, $games)
            : $this->expectedPerGame($ratingBefore, $games);
    }

    /**
     * @param  list<array{opponent_rating: int, score: float}>  $games
     */
    public function score(array $games): float
    {
        return array_sum(array_column($games, 'score'));
    }

    /**
     * @param  list<array{opponent_rating: int, score: float}>  $games
     */
    private function expectedAverage(int $ratingBefore, array $games): float
    {
        $count = count($games);
        $averageOpponent = array_sum(array_column($games, 'opponent_rating')) / $count;

        return $count / (1 + 10 ** (($averageOpponent - $ratingBefore) / 400));
    }

    /**
     * @param  list<array{opponent_rating: int, score: float}>  $games
     */
    private function expectedPerGame(int $ratingBefore, array $games): float
    {
        $expected = 0.0;

        foreach ($games as $game) {
            $expected += 1 / (1 + 10 ** (($game['opponent_rating'] - $ratingBefore) / 400));
        }

        return $expected;
    }

    private function isRatingGame(Pairing $pairing): bool
    {
        return ! $pairing->isBye()
            && ! $pairing->result->isPending()
            && $pairing->result !== PairingResult::DoubleForfeit;
    }
}
