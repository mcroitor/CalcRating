<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('players.export.title_print') }}</title>
    <style>
        body {
            font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            margin: 20px auto;
            max-width: 1000px;
            color: #1f2937;
            padding: 0 15px;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid #4f46e5;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        .title {
            font-size: 24px;
            font-weight: bold;
            color: #111827;
            margin: 0;
        }
        .meta {
            font-size: 13px;
            color: #6b7280;
            margin-top: 4px;
        }
        .btn-print {
            padding: 8px 16px;
            background-color: #4f46e5;
            color: #ffffff;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
        }
        .btn-print:hover {
            background-color: #4338ca;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }
        th {
            background-color: #f3f4f6;
            color: #374151;
            font-weight: 600;
            padding: 10px 12px;
            text-align: left;
            border-bottom: 2px solid #e5e7eb;
        }
        td {
            padding: 10px 12px;
            border-bottom: 1px solid #e5e7eb;
        }
        tr:hover td {
            background-color: #f9fafb;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .font-bold { font-weight: bold; }
        @media print {
            .btn-print { display: none; }
            body { margin: 0; max-width: 100%; }
        }
    </style>
</head>
<body>
    <div class="header">
        <div>
            <h1 class="title">{{ __('players.export.title') }}</h1>
            <div class="meta">{{ __('players.export.generated', ['date' => $generated_at]) }} &bull; {{ __('players.export.total', ['count' => $players->count()]) }}</div>
        </div>
        <button class="btn-print" onclick="window.print()">{{ __('exports.print') }}</button>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 8%;" class="text-center">{{ __('players.export.id') }}</th>
                <th style="width: 32%;">{{ __('players.export.name') }}</th>
                <th style="width: 18%;" class="text-center">{{ __('players.export.birth_date') }}</th>
                <th style="width: 12%;" class="text-center">{{ __('players.export.age') }}</th>
                <th style="width: 18%;">{{ __('players.export.category_full') }}</th>
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
</body>
</html>
