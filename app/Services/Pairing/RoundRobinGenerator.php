<?php

namespace App\Services\Pairing;

use App\Enums\PairingResult;
use App\Exceptions\DomainException;
use App\Models\Tournament;

class RoundRobinGenerator
{
    /**
     * Generate the complete Berger round-robin schedule.
     *
     * @return array<int, list<array{white_player_id: int, black_player_id: int|null, result: PairingResult}>>
     */
    public function generate(Tournament $tournament): array
    {
        $players = $tournament->participants()
            ->orderBy('id')
            ->pluck('player_id')
            ->all();

        if (count($players) < 2) {
            throw new DomainException(__('pairings.errors.min_participants'));
        }

        if (count($players) % 2 === 1) {
            $players[] = null; // Dummy participant for a bye.
        }

        $slots = $players;
        $half = intdiv(count($slots), 2);
        $roundsCount = count($slots) - 1;
        $schedule = [];

        for ($round = 0; $round < $roundsCount; $round++) {
            $games = [];

            for ($i = 0; $i < $half; $i++) {
                $first = $slots[$i];
                $second = $slots[count($slots) - 1 - $i];

                if ($first === null || $second === null) {
                    continue;
                }

                [$white, $black] = ($round + $i) % 2 === 0
                    ? [$first, $second]
                    : [$second, $first];

                $games[] = $this->game($white, $black);

                for ($game = 1; $game < $tournament->games_per_round; $game++) {
                    $games[] = $game % 2 === 1
                        ? $this->game($black, $white)
                        : $this->game($white, $black);
                }
            }

            $schedule[$round + 1] = $games;

            // Berger rotation: the first slot stays fixed, the rest rotate clockwise.
            $last = array_pop($slots);
            array_splice($slots, 1, 0, [$last]);
        }

        return $schedule;
    }

    /**
     * @return array{white_player_id: int, black_player_id: int, result: PairingResult}
     */
    private function game(int $white, int $black): array
    {
        return [
            'white_player_id' => $white,
            'black_player_id' => $black,
            'result' => PairingResult::Pending,
        ];
    }
}
