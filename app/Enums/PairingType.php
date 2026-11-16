<?php

namespace App\Enums;

enum PairingType: string
{
    case RoundRobin = 'round_robin';
    case Swiss = 'swiss';
    case Olympic = 'olympic';

    public function label(): string
    {
        return match ($this) {
            self::RoundRobin => __('enums.pairing_type.round_robin'),
            self::Swiss => __('enums.pairing_type.swiss'),
            self::Olympic => __('enums.pairing_type.olympic'),
        };
    }
}
