<?php

namespace App\Enums;

enum ClubStaffRole: string
{
    case Manager = 'manager';
    case Arbiter = 'arbiter';

    public function label(): string
    {
        return __("enums.club_staff_role.{$this->value}");
    }
}
