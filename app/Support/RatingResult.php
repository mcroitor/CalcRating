<?php

namespace App\Support;

final class RatingResult
{
    public function __construct(
        public readonly int $participantId,
        public readonly int $playerId,
        public readonly int $ratingBefore,
        public readonly int $ratingAfter,
        public readonly int $games,
        public readonly float $score,
        public readonly float $expected,
        public readonly int $kFactor,
    ) {}

    public function delta(): int
    {
        return $this->ratingAfter - $this->ratingBefore;
    }
}
