<?php

namespace App\Enums;

enum PairingResult: string
{
    case WhiteWin = '1-0';
    case BlackWin = '0-1';
    case Draw = '1/2-1/2';
    case DoubleForfeit = '0-0';
    case Pending = '*';

    public function label(): string
    {
        return match ($this) {
            self::WhiteWin => __('enums.pairing_result.white_win'),
            self::BlackWin => __('enums.pairing_result.black_win'),
            self::Draw => __('enums.pairing_result.draw'),
            self::DoubleForfeit => __('enums.pairing_result.double_forfeit'),
            self::Pending => __('enums.pairing_result.pending'),
        };
    }

    public function whitePoints(): float
    {
        return match ($this) {
            self::WhiteWin => 1.0,
            self::Draw => 0.5,
            default => 0.0,
        };
    }

    public function blackPoints(): float
    {
        return match ($this) {
            self::BlackWin => 1.0,
            self::Draw => 0.5,
            default => 0.0,
        };
    }

    public function isPending(): bool
    {
        return $this === self::Pending;
    }

    public function isDraw(): bool
    {
        return $this === self::Draw;
    }
}
