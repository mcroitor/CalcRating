<?php

namespace App\Enums;

enum UserRole: string
{
    case Guest = 'guest';
    case Arbiter = 'arbiter';
    case Admin = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::Guest => __('enums.user_role.guest'),
            self::Arbiter => __('enums.user_role.arbiter'),
            self::Admin => __('enums.user_role.admin'),
        };
    }
}
