<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('tournaments.export.title') }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; }
        h1, h2 { margin: 0 0 8px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        th, td { border: 1px solid #999; padding: 4px; }
        th { background: #e5e7eb; }
    </style>
</head>
<body>
    <h1>{{ $tournament->name }}</h1>
    @php
        $dates = \App\Support\DateFormatter::format($tournament->start_date).($tournament->end_date ? ' — '.\App\Support\DateFormatter::format($tournament->end_date) : '');
        $arbiter = trim(($tournament->arbiter?->surname ?? '').' '.($tournament->arbiter?->name ?? ''));
    @endphp
    <p>{{ __('tournaments.export.club', ['club' => $tournament->club?->name ?? '—']) }}. {{ __('tournaments.export.date', ['dates' => $dates]) }}. {{ __('tournaments.export.location', ['location' => $tournament->location ?? '—']) }}. {{ __('tournaments.export.arbiter', ['arbiter' => $arbiter !== '' ? $arbiter : '—']) }}. {{ __('tournaments.export.system', ['system' => $tournament->pairing_type->label()]) }}. {{ __('tournaments.export.mode', ['mode' => $tournament->rating_mode->label()]) }}.</p>

    <h2>{{ __('tournaments.export.standings') }}</h2>
    <table>
        <thead>
            <tr>
                <th>{{ __('tournaments.export.col_place') }}</th>
                <th>{{ __('tournaments.export.col_player') }}</th>
                <th>{{ __('tournaments.export.col_title') }}</th>
                <th>{{ __('tournaments.export.col_rating_before') }}</th>
                <th>{{ __('tournaments.export.col_points') }}</th>
                <th>{{ __('tournaments.export.col_rating_after') }}</th>
                <th>{{ __('tournaments.export.col_delta') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach($standings as $index => $row)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $row->playerName }}</td>
                    <td>{{ $row->playerTitle?->label() ?? '—' }}</td>
                    <td>{{ $row->ratingBefore }}</td>
                    <td>{{ number_format($row->points, 1, '.', '') }}</td>
                    <td>{{ $row->ratingAfter ?? '—' }}</td>
                    <td>{{ $row->ratingAfter !== null ? ($row->ratingAfter - $row->ratingBefore) : '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <h2>{{ __('tournaments.export.rounds') }}</h2>
    @foreach($pairingsByRound as $roundNumber => $roundPairings)
        <h3>{{ __('tournaments.export.round', ['round' => $roundNumber]) }}</h3>
        <table>
            <thead>
                <tr>
                    <th>{{ __('tournaments.export.col_board') }}</th>
                    <th>{{ __('tournaments.export.col_white') }}</th>
                    <th>{{ __('tournaments.export.col_result') }}</th>
                    <th>{{ __('tournaments.export.col_black') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($roundPairings as $pairing)
                    <tr>
                        <td>{{ $pairing->board_number }}</td>
                        <td>{{ $pairing->whitePlayer?->full_name ?? '—' }}</td>
                        <td>{{ $pairing->result->value }}</td>
                        <td>{{ $pairing->blackPlayer?->full_name ?? __('exports.bye') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endforeach

    <p>{{ __('exports.generated_at', ['date' => $generated_at]) }}</p>
</body>
</html>