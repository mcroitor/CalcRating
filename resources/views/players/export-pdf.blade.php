<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ __('players.export.title') }}</title>
    <style>
        @page {
            margin: 20mm 15mm 20mm 15mm;
        }
        body {
            font-family: "DejaVu Sans", sans-serif;
            font-size: 10pt;
            color: #333333;
            line-height: 1.4;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #4f46e5;
            padding-bottom: 10px;
        }
        .title {
            font-size: 16pt;
            font-weight: bold;
            color: #1e1b4b;
            margin: 0;
        }
        .meta {
            font-size: 9pt;
            color: #6b7280;
            margin-top: 5px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        th {
            background-color: #4f46e5;
            color: #ffffff;
            font-weight: bold;
            font-size: 9pt;
            padding: 8px 6px;
            text-align: left;
            border: 1px solid #4338ca;
        }
        td {
            padding: 6px;
            border: 1px solid #e5e7eb;
            font-size: 9pt;
        }
        tr:nth-child(even) td {
            background-color: #f9fafb;
        }
        .text-center {
            text-align: center;
        }
        .text-right {
            text-align: right;
        }
        .font-bold {
            font-weight: bold;
        }
        .footer {
            position: fixed;
            bottom: -10mm;
            left: 0;
            right: 0;
            font-size: 8pt;
            color: #9ca3af;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1 class="title">{{ __('players.export.title') }}</h1>
        <div class="meta">{{ __('players.export.generated', ['date' => $generated_at]) }} &bull; {{ __('players.export.total', ['count' => $players->count()]) }}</div>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 8%;" class="text-center">{{ __('players.export.id') }}</th>
                <th style="width: 32%;">{{ __('players.export.name') }}</th>
                <th style="width: 18%;" class="text-center">{{ __('players.export.birth_date') }}</th>
                <th style="width: 12%;" class="text-center">{{ __('players.export.age') }}</th>
                <th style="width: 18%;">{{ __('players.export.category') }}</th>
                <th style="width: 12%;" class="text-right">{{ __('players.export.rating') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse($players as $player)
                <tr>
                    <td class="text-center font-bold">#{{ $player->id }}</td>
                    <td class="font-bold">{{ $player->full_name }}</td>
                    <td class="text-center">{{ \App\Support\DateFormatter::format($player->birth_date) ?? '—' }}</td>
                    <td class="text-center">{{ $player->age ? trans_choice('common.years_old', $player->age, ['age' => $player->age]) : '—' }}</td>
                    <td>{{ $player->title?->label() ?? __('enums.chess_title.none') }}</td>
                    <td class="text-right font-bold">{{ $player->current_rating }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="text-center" style="padding: 20px; color: #6b7280;">{{ __('players.export.empty') }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        {{ __('players.export.footer') }}
    </div>
</body>
</html>
