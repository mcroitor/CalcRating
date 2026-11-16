<?php

namespace App\Enums;

enum TournamentStatus: string
{
    case Draft = 'draft';
    case InProgress = 'in_progress';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => __('enums.tournament_status.draft'),
            self::InProgress => __('enums.tournament_status.in_progress'),
            self::Completed => __('enums.tournament_status.completed'),
        };
    }
}
