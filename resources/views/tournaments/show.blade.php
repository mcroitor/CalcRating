<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4">
            <div>
                <div class="flex items-center gap-3 flex-wrap">
                    <h2 class="font-semibold text-2xl text-gray-800 leading-tight">
                        {{ $tournament->name }}
                    </h2>
                    @php
                        $statusClasses = match ($tournament->status) {
                            App\Enums\TournamentStatus::Draft => 'bg-gray-100 text-gray-700',
                            App\Enums\TournamentStatus::InProgress => 'bg-green-100 text-green-800',
                            App\Enums\TournamentStatus::Completed => 'bg-blue-100 text-blue-800',
                        };
                    @endphp
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold {{ $statusClasses }}">
                        {{ $tournament->status->label() }}
                    </span>
                </div>
                <div class="text-sm text-gray-500 mt-1">
                    {{ \App\Support\DateFormatter::format($tournament->start_date) }}@if($tournament->end_date) — {{ \App\Support\DateFormatter::format($tournament->end_date) }}@endif
                    @if($tournament->location)
                        &bull; {{ $tournament->location }}
                    @endif
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('tournaments.index') }}" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md text-sm font-medium hover:bg-gray-300 transition">
                    {{ __('tournaments.show.back') }}
                </a>

                @can('update', $tournament)
                    <a href="{{ route('tournaments.edit', $tournament) }}" class="px-4 py-2 bg-indigo-600 text-white rounded-md text-sm font-medium hover:bg-indigo-700 transition">
                        {{ __('tournaments.show.edit') }}
                    </a>
                @endcan

                @can('export', $tournament)
                    <a href="{{ route('tournaments.export', [$tournament, 'xlsx']) }}" class="px-4 py-2 bg-emerald-600 text-white rounded-md text-sm font-medium hover:bg-emerald-700 transition">
                        XLSX
                    </a>
                    <a href="{{ route('tournaments.export', [$tournament, 'pdf']) }}" class="px-4 py-2 bg-rose-600 text-white rounded-md text-sm font-medium hover:bg-rose-700 transition">
                        PDF
                    </a>
                    <a href="{{ route('tournaments.export', [$tournament, 'html']) }}" class="px-4 py-2 bg-sky-600 text-white rounded-md text-sm font-medium hover:bg-sky-700 transition">
                        HTML
                    </a>
                @endcan

                @can('start', $tournament)
                    <form method="POST" action="{{ route('tournaments.start', $tournament) }}"
                          onsubmit="return confirm('{{ __('tournaments.show.start_confirm') }}');">
                        @csrf
                        <input type="hidden" name="version" value="{{ $tournament->version }}">
                        <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-md text-sm font-medium hover:bg-green-700 transition">
                            {{ __('tournaments.show.start') }}
                        </button>
                    </form>
                @endcan

                @can('delete', $tournament)
                    <form method="POST" action="{{ route('tournaments.destroy', $tournament) }}"
                          onsubmit="return confirm('{{ __('tournaments.show.delete_confirm') }}');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-md text-sm font-medium hover:bg-red-700 transition">
                            {{ __('tournaments.show.delete') }}
                        </button>
                    </form>
                @endcan
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if(session('success'))
                <div class="p-4 bg-green-50 border-l-4 border-green-400 text-green-700 rounded-md">
                    {{ session('success') }}
                </div>
            @endif

            @if(session('error'))
                <div class="p-4 bg-red-50 border-l-4 border-red-400 text-red-700 rounded-md">
                    {{ session('error') }}
                </div>
            @endif

            @if($tournament->isDraft() && $canManage)
                <div class="p-4 bg-blue-50 border-l-4 border-blue-400 text-blue-800 rounded-md text-sm">
                    {{ __('tournaments.show.draft_notice') }}
                </div>
            @endif

            <!-- General information -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-white p-5 shadow sm:rounded-lg">
                    <div class="text-xs uppercase font-semibold text-gray-500 tracking-wider">{{ __('tournaments.show.pairing_type') }}</div>
                    <div class="text-lg font-bold text-gray-900 mt-1">{{ $tournament->pairing_type->label() }}</div>
                </div>

                <div class="bg-white p-5 shadow sm:rounded-lg">
                    <div class="text-xs uppercase font-semibold text-gray-500 tracking-wider">{{ __('tournaments.show.rating_mode') }}</div>
                    <div class="text-lg font-bold text-gray-900 mt-1">{{ $tournament->rating_mode->shortLabel() }}</div>
                    <div class="text-xs text-gray-500 mt-1">{{ $tournament->rating_mode->description() }}</div>
                </div>

                <div class="bg-white p-5 shadow sm:rounded-lg">
                    <div class="text-xs uppercase font-semibold text-gray-500 tracking-wider">{{ __('tournaments.show.rounds_and_games') }}</div>
                    <div class="text-lg font-bold text-gray-900 mt-1">{{ __('tournaments.show.rounds_count', ['count' => $tournament->rounds_count]) }}</div>
                    <div class="text-xs text-gray-500 mt-1">{{ __('tournaments.show.games_per_round', ['count' => $tournament->games_per_round]) }}</div>
                </div>

                <div class="bg-white p-5 shadow sm:rounded-lg">
                    <div class="text-xs uppercase font-semibold text-gray-500 tracking-wider">{{ __('tournaments.show.arbiter') }}</div>
                    <div class="text-lg font-bold text-gray-900 mt-1">
                        {{ $tournament->arbiter?->surname }} {{ $tournament->arbiter?->name }}
                    </div>
                    <div class="text-xs text-gray-500 mt-1">
                        {{ __('tournaments.show.participants_count', ['count' => $tournament->participants->count()]) }}
                        @if($tournament->club)
                            &bull;
                            <a href="{{ route('clubs.show', $tournament->club) }}" class="text-indigo-600 hover:underline">
                                {{ $tournament->club->name }}
                            </a>
                        @endif
                    </div>
                </div>
            </div>

            @if($tournament->notes)
                <div class="bg-white p-6 shadow sm:rounded-lg">
                    <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wider mb-2">{{ __('tournaments.show.notes_title') }}</h3>
                    <p class="text-gray-700 whitespace-pre-line text-sm">{{ $tournament->notes }}</p>
                </div>
            @endif

            <!-- Participant management (draft only) -->
            @if($canManage)
                <div id="participants" class="bg-white shadow sm:rounded-lg">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-bold text-gray-900">{{ __('tournaments.show.add_participants') }}</h3>
                        <p class="text-sm text-gray-500 mt-1">{{ __('tournaments.show.add_participants_hint') }}</p>
                    </div>

                    <div class="p-6 space-y-4">
                        <form method="GET" action="{{ route('tournaments.show', $tournament) }}#participants" class="flex flex-col sm:flex-row sm:items-center gap-3">
                            <input type="text" name="player_search" value="{{ request('player_search') }}" placeholder="{{ __('tournaments.show.player_search_placeholder') }}"
                                   class="flex-1 text-sm border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                            <label class="inline-flex items-center gap-2 text-sm text-gray-700 whitespace-nowrap">
                                <input type="checkbox" name="club_members_only" value="1" @checked(request()->boolean('club_members_only'))
                                       class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                <span>{{ __('tournaments.show.club_members_only') }}</span>
                            </label>
                            <button type="submit" class="px-4 py-2 bg-gray-800 text-white rounded-md text-sm font-medium hover:bg-gray-700 transition">
                                {{ __('common.search') }}
                            </button>
                            @if(request('player_search') || request()->boolean('club_members_only'))
                                <a href="{{ route('tournaments.show', $tournament) }}#participants" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md text-sm font-medium hover:bg-gray-300 transition text-center">
                                    {{ __('common.reset') }}
                                </a>
                            @endif
                        </form>

                        @if($availablePlayers->isEmpty())
                            <p class="text-sm text-gray-500">
                                {{ request('player_search') ? __('tournaments.show.no_players_found') : __('tournaments.show.all_players_added') }}
                            </p>
                        @else
                            <div class="divide-y divide-gray-200 border border-gray-200 rounded-md">
                                @foreach($availablePlayers as $player)
                                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 p-3">
                                        <div>
                                            <div class="font-medium text-gray-900">{{ $player->full_name }}</div>
                                            <div class="text-xs text-gray-500">
                                                {{ $player->title->label() }} &bull; {{ __('tournaments.show.player_rating', ['rating' => $player->current_rating]) }}
                                            </div>
                                        </div>
                                        <form method="POST" action="{{ route('tournaments.participants.store', $tournament) }}">
                                            @csrf
                                            <input type="hidden" name="player_id" value="{{ $player->id }}">
                                            <input type="hidden" name="version" value="{{ $tournament->version }}">
                                            <button type="submit" class="px-3 py-1.5 bg-indigo-600 text-white rounded-md text-xs font-medium hover:bg-indigo-700 transition">
                                                {{ __('tournaments.show.add') }}
                                            </button>
                                        </form>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            <!-- Roster -->
            <div class="bg-white shadow sm:rounded-lg overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                    <div>
                        <h3 class="text-lg font-bold text-gray-900">{{ __('tournaments.show.roster') }}</h3>
                        <p class="text-xs text-gray-500 mt-0.5">
                            @if($tournament->isDraft())
                                {{ __('tournaments.show.roster_draft_hint') }}
                            @else
                                {{ __('tournaments.show.roster_locked_hint') }}
                            @endif
                        </p>
                    </div>
                    <span class="text-sm text-gray-500">{{ __('tournaments.show.total', ['count' => $tournament->participants->count()]) }}</span>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-left text-sm">
                        <thead class="bg-gray-50 text-gray-500 uppercase tracking-wider text-xs">
                            <tr>
                                <th scope="col" class="px-6 py-3">{{ __('tournaments.show.col_number') }}</th>
                                <th scope="col" class="px-6 py-3">{{ __('tournaments.show.col_player') }}</th>
                                <th scope="col" class="px-6 py-3">{{ __('tournaments.show.col_title') }}</th>
                                <th scope="col" class="px-6 py-3 text-right">{{ __('tournaments.show.col_rating_before') }}</th>
                                <th scope="col" class="px-6 py-3 text-right">{{ __('tournaments.show.col_points') }}</th>
                                @if($canManage)
                                    <th scope="col" class="px-6 py-3 text-right">{{ __('tournaments.show.col_actions') }}</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            @forelse($tournament->participants as $participant)
                                <tr class="hover:bg-gray-50 transition">
                                    <td class="px-6 py-4 whitespace-nowrap text-gray-500 font-mono">#{{ $participant->id }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap font-medium text-gray-900">
                                        <a href="{{ route('players.show', $participant->player) }}" class="text-indigo-600 hover:underline">
                                            {{ $participant->player->full_name }}
                                        </a>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-gray-600">{{ $participant->player->title->label() }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right font-bold text-gray-900">{{ $participant->rating_before }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-gray-700">{{ number_format((float) $participant->points, 1, '.', '') }}</td>
                                    @if($canManage)
                                        <td class="px-6 py-4 whitespace-nowrap text-right">
                                            <form method="POST" action="{{ route('tournaments.participants.destroy', [$tournament, $participant]) }}"
                                                  onsubmit="return confirm('{{ __('tournaments.show.remove_confirm') }}');">
                                                @csrf
                                                @method('DELETE')
                                                <input type="hidden" name="version" value="{{ $tournament->version }}">
                                                <button type="submit" class="text-red-600 hover:text-red-800 text-sm">
                                                    {{ __('tournaments.show.remove') }}
                                                </button>
                                            </form>
                                        </td>
                                    @endif
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ $canManage ? 6 : 5 }}" class="px-6 py-8 text-center text-gray-500">
                                        {{ __('tournaments.show.no_participants') }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Pairings and rounds -->
            @if(! $tournament->isDraft() || $tournament->pairings->isNotEmpty())
                <div id="pairings" class="bg-white shadow sm:rounded-lg overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200 flex flex-col lg:flex-row lg:items-center justify-between gap-3">
                        <div>
                            <h3 class="text-lg font-bold text-gray-900">{{ __('tournaments.show.pairings_title') }}</h3>
                            <p class="text-xs text-gray-500 mt-0.5">
                                @if($tournament->pairing_type === App\Enums\PairingType::RoundRobin)
                                    {{ __('tournaments.show.pairing_hint_round_robin') }}
                                @elseif($tournament->pairing_type === App\Enums\PairingType::Swiss)
                                    {{ __('tournaments.show.pairing_hint_swiss') }}
                                @else
                                    {{ __('tournaments.show.pairing_hint_olympic') }}
                                @endif
                            </p>
                        </div>

                        @if($canManagePairings)
                            <div class="flex flex-col items-start lg:items-end gap-1">
                                @if($tournament->pairing_type === App\Enums\PairingType::RoundRobin)
                                    @if($tournament->pairings->isEmpty())
                                        <form method="POST" action="{{ route('tournaments.rounds.generate', $tournament) }}"
                                              onsubmit="return confirm('{{ __('tournaments.show.generate_schedule_confirm') }}');">
                                            @csrf
                                            <input type="hidden" name="version" value="{{ $tournament->version }}">
                                            <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md text-sm font-medium hover:bg-indigo-700 transition">
                                                {{ __('tournaments.show.generate_schedule') }}
                                            </button>
                                        </form>
                                    @else
                                        <span class="text-sm text-gray-500">{{ __('tournaments.show.generated_rounds_count', ['count' => $pairingsByRound->count()]) }}</span>
                                    @endif
                                @elseif($canGenerateNextRound)
                                    <form method="POST" action="{{ route('tournaments.rounds.generate', $tournament) }}"
                                          onsubmit="return confirm('{{ __('tournaments.show.generate_round_confirm', ['round' => $nextRoundNumber]) }}');">
                                        @csrf
                                        <input type="hidden" name="version" value="{{ $tournament->version }}">
                                        <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md text-sm font-medium hover:bg-indigo-700 transition">
                                            {{ __('tournaments.show.generate_round', ['round' => $nextRoundNumber]) }}
                                        </button>
                                    </form>
                                @else
                                    <span class="text-xs text-amber-800 bg-amber-50 border border-amber-200 rounded-md px-3 py-2">
                                        {{ $generationBlockReason }}
                                    </span>
                                @endif
                            </div>
                        @endif
                    </div>

                    @if($pairingsByRound->isEmpty())
                        <div class="px-6 py-8 text-center text-gray-500 text-sm">
                            {{ __('tournaments.show.no_pairings') }}
                        </div>
                    @else
                        <div class="divide-y divide-gray-200">
                            @foreach($pairingsByRound as $roundNumber => $roundPairings)
                                <div class="p-6">
                                    <h4 class="text-base font-semibold text-gray-800 mb-3">{{ __('tournaments.show.round', ['round' => $roundNumber]) }}</h4>

                                    <div class="overflow-x-auto">
                                        <table class="min-w-full divide-y divide-gray-200 text-left text-sm">
                                            <thead class="bg-gray-50 text-gray-500 uppercase tracking-wider text-xs">
                                                <tr>
                                                    <th scope="col" class="px-4 py-2">{{ __('tournaments.show.col_board') }}</th>
                                                    <th scope="col" class="px-4 py-2">{{ __('tournaments.show.col_white') }}</th>
                                                    <th scope="col" class="px-4 py-2">{{ __('tournaments.show.col_result') }}</th>
                                                    <th scope="col" class="px-4 py-2">{{ __('tournaments.show.col_black') }}</th>
                                                    @if($canManagePairings)
                                                        <th scope="col" class="px-4 py-2 text-right">{{ __('tournaments.show.col_actions') }}</th>
                                                    @endif
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-gray-200 bg-white">
                                                @foreach($roundPairings as $pairing)
                                                    <tr class="hover:bg-gray-50 transition align-top">
                                                        <td class="px-4 py-3 font-mono text-gray-500">{{ $pairing->board_number }}</td>
                                                        <td class="px-4 py-3 font-medium text-gray-900">{{ $pairing->whitePlayer?->full_name ?? '—' }}</td>
                                                        <td class="px-4 py-3">
                                                            @if($pairing->isBye())
                                                                <span class="inline-flex px-2 py-0.5 rounded bg-gray-100 text-gray-700 text-xs font-semibold">{{ __('tournaments.show.bye_result') }}</span>
                                                            @else
                                                                <span class="font-mono text-gray-900">{{ $pairing->result->value }}</span>
                                                                @if($pairing->advancedPlayer)
                                                                    <div class="text-xs text-gray-500 mt-1">{{ __('tournaments.show.advanced', ['name' => $pairing->advancedPlayer->full_name]) }}</div>
                                                                @endif
                                                            @endif
                                                        </td>
                                                        <td class="px-4 py-3 text-gray-700">{{ $pairing->blackPlayer?->full_name ?? '—' }}</td>
                                                        @if($canManagePairings)
                                                            <td class="px-4 py-3 text-right">
                                                                @if($pairing->isBye())
                                                                    <span class="text-xs text-gray-400">{{ __('tournaments.show.automatic') }}</span>
                                                                @else
                                                                    <form method="POST" action="{{ route('tournaments.pairings.result', [$tournament, $pairing]) }}"
                                                                          class="flex flex-col sm:flex-row sm:items-center sm:justify-end gap-2">
                                                                        @csrf
                                                                        @method('PUT')
                                                                        <input type="hidden" name="version" value="{{ $pairing->version }}">
                                                                        <select name="result" class="text-xs border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                                                            @foreach([App\Enums\PairingResult::WhiteWin, App\Enums\PairingResult::BlackWin, App\Enums\PairingResult::Draw, App\Enums\PairingResult::DoubleForfeit] as $resultOption)
                                                                                <option value="{{ $resultOption->value }}" @selected($pairing->result === $resultOption)>{{ $resultOption->label() }}</option>
                                                                            @endforeach
                                                                        </select>
                                                                        @if($tournament->pairing_type === App\Enums\PairingType::Olympic)
                                                                            <fieldset class="text-xs text-gray-700">
                                                                                <legend class="text-gray-500 mb-1">{{ __('tournaments.show.advanced_legend') }}</legend>
                                                                                <label class="inline-flex items-center gap-1 mr-2">
                                                                                    <input type="radio" name="advanced_player_id"
                                                                                           value="{{ $pairing->white_player_id }}"
                                                                                           @checked($pairing->advanced_player_id === $pairing->white_player_id)>
                                                                                    {{ $pairing->whitePlayer?->last_name }}
                                                                                </label>
                                                                                <label class="inline-flex items-center gap-1">
                                                                                    <input type="radio" name="advanced_player_id"
                                                                                           value="{{ $pairing->black_player_id }}"
                                                                                           @checked($pairing->advanced_player_id === $pairing->black_player_id)>
                                                                                    {{ $pairing->blackPlayer?->last_name }}
                                                                                </label>
                                                                            </fieldset>
                                                                        @endif
                                                                        <button type="submit" class="px-3 py-1.5 bg-indigo-600 text-white rounded-md text-xs font-medium hover:bg-indigo-700 transition">
                                                                            {{ __('common.save') }}
                                                                        </button>
                                                                    </form>
                                                                @endif
                                                            </td>
                                                        @endif
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endif

            <!-- Completion -->
            @if($tournament->isCompleted())
                <div class="p-4 bg-blue-50 border-l-4 border-blue-400 text-blue-800 rounded-md text-sm">
                    {{ __('tournaments.show.completed_notice') }}
                </div>
            @elseif($canComplete)
                <div class="bg-white shadow sm:rounded-lg overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-bold text-gray-900">{{ __('tournaments.show.completion_title') }}</h3>
                        <p class="text-xs text-gray-500 mt-0.5">
                            {{ __('tournaments.show.completion_hint') }}
                        </p>
                    </div>

                    <div class="px-6 py-4 space-y-3">
                        @if($completionBlockReason)
                            <div class="px-3 py-2 rounded bg-amber-50 border border-amber-200 text-amber-800 text-sm">
                                {{ $completionBlockReason }}
                            </div>
                        @elseif($overlappingParticipants->isNotEmpty())
                            <div class="px-3 py-2 rounded bg-amber-50 border border-amber-200 text-amber-800 text-sm">
                                {{ __('tournaments.show.overlap_notice') }}
                            </div>
                            <ul class="list-disc list-inside text-sm text-gray-700">
                                @foreach($overlappingParticipants as $participant)
                                    <li>
                                        {{ __('tournaments.show.overlap_line', [
                                            'name' => $participant->player->full_name,
                                            'current' => $participant->player->current_rating,
                                            'before' => $participant->rating_before,
                                        ]) }}
                                    </li>
                                @endforeach
                            </ul>
                            <form method="POST" action="{{ route('tournaments.complete', $tournament) }}"
                                  onsubmit="return confirm('{{ __('tournaments.show.complete_overlap_confirm') }}');">
                                @csrf
                                <input type="hidden" name="version" value="{{ $tournament->version }}">
                                <input type="hidden" name="confirm_overlap" value="1">
                                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md text-sm font-medium hover:bg-blue-700 transition">
                                    {{ __('tournaments.show.complete_overlap') }}
                                </button>
                            </form>
                        @else
                            <form method="POST" action="{{ route('tournaments.complete', $tournament) }}"
                                  onsubmit="return confirm('{{ __('tournaments.show.complete_confirm') }}');">
                                @csrf
                                <input type="hidden" name="version" value="{{ $tournament->version }}">
                                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md text-sm font-medium hover:bg-blue-700 transition">
                                    {{ __('tournaments.show.complete') }}
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            @endif

            <!-- Standings -->
            <div class="bg-white shadow sm:rounded-lg overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-bold text-gray-900">{{ __('tournaments.show.standings') }}</h3>
                    <p class="text-xs text-gray-500 mt-0.5">
                        @if($tournament->pairing_type === App\Enums\PairingType::Olympic)
                            {{ __('tournaments.show.sort_olympic') }}
                        @else
                            {{ __('tournaments.show.sort_standard') }}
                        @endif
                    </p>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-left text-sm">
                        <thead class="bg-gray-50 text-gray-500 uppercase tracking-wider text-xs">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-center">{{ __('tournaments.show.col_place') }}</th>
                                <th scope="col" class="px-6 py-3">{{ __('tournaments.show.col_player') }}</th>
                                <th scope="col" class="px-6 py-3 text-right">{{ __('tournaments.show.col_rating_before') }}</th>
                                <th scope="col" class="px-6 py-3 text-right">{{ __('tournaments.show.col_points') }}</th>
                                <th scope="col" class="px-6 py-3 text-right">{{ __('tournaments.show.col_rating_after') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            @forelse($standings as $row)
                                <tr class="hover:bg-gray-50 transition">
                                    <td class="px-6 py-4 whitespace-nowrap text-center font-bold text-gray-900">{{ $loop->iteration }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap font-medium text-gray-900">{{ $row->playerName }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-gray-700">{{ $row->ratingBefore }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right font-bold text-gray-900">{{ number_format($row->points, 1, '.', '') }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-gray-700">
    {{ $row->ratingAfter ?? '—' }}
    @if($row->ratingAfter !== null)
        @php $delta = $row->ratingAfter - $row->ratingBefore; @endphp
        <span class="block text-xs {{ $delta >= 0 ? 'text-green-600' : 'text-red-600' }}">
            {{ $delta >= 0 ? '+'.$delta : $delta }}
        </span>
    @endif
</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-6 py-8 text-center text-gray-500">
                                        {{ __('tournaments.show.empty_standings') }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
