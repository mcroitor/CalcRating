<?php

namespace App\Enums;

enum ChessTitle: string
{
    case None = 'None';
    case IV = 'IV';
    case III = 'III';
    case II = 'II';
    case I = 'I';
    case CM = 'CM';
    case WCM = 'WCM';
    case FM = 'FM';
    case WFM = 'WFM';
    case IM = 'IM';
    case WIM = 'WIM';
    case GM = 'GM';
    case WGM = 'WGM';

    public function label(): string
    {
        return match ($this) {
            self::None => __('enums.chess_title.none'),
            self::IV => __('enums.chess_title.iv'),
            self::III => __('enums.chess_title.iii'),
            self::II => __('enums.chess_title.ii'),
            self::I => __('enums.chess_title.i'),
            self::CM => __('enums.chess_title.cm'),
            self::WCM => __('enums.chess_title.wcm'),
            self::FM => __('enums.chess_title.fm'),
            self::WFM => __('enums.chess_title.wfm'),
            self::IM => __('enums.chess_title.im'),
            self::WIM => __('enums.chess_title.wim'),
            self::GM => __('enums.chess_title.gm'),
            self::WGM => __('enums.chess_title.wgm'),
        };
    }
}
