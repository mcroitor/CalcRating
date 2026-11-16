<?php

namespace App\Enums;

enum RatingMode: string
{
    case Average = 'average';
    case PerGame = 'per_game';

    public function label(): string
    {
        return match ($this) {
            self::Average => __('enums.rating_mode.average'),
            self::PerGame => __('enums.rating_mode.per_game'),
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Average => __('enums.rating_mode.average_description'),
            self::PerGame => __('enums.rating_mode.per_game_description'),
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::Average => __('enums.rating_mode.average_short'),
            self::PerGame => __('enums.rating_mode.per_game_short'),
        };
    }
}
