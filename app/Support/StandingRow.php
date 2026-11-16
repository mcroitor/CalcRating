<?php

namespace App\Support;

use App\Enums\ChessTitle;

final class StandingRow
{
    /**
     * @param  array<int, float>  $headToHead  Score keyed by opponent player ID (1 win, 0.5 draw, 0 loss).
     */
    public function __construct(
        public readonly int $participantId,
        public readonly int $playerId,
        public readonly string $playerName,
        public readonly float $points = 0.0,
        public readonly int $wins = 0,
        public readonly array $headToHead = [],
        public readonly int $ratingBefore = 0,
        public readonly ?int $ratingAfter = null,
        public readonly ?int $eliminatedRound = null,
        public readonly ?ChessTitle $playerTitle = null,
    ) {}

    public function headToHeadScoreAgainst(int $opponentPlayerId): float
    {
        return $this->headToHead[$opponentPlayerId] ?? 0.0;
    }
}
