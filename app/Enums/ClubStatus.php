<?php

namespace App\Enums;

enum ClubStatus: string
{
    case Active = 'active';
    case Archived = 'archived';

    public function label(): string
    {
        return __("enums.club_status.{$this->value}");
    }
}
