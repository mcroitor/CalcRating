<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('tournaments.index.title') }}
            </h2>
            @can('create', App\Models\Tournament::class)
                <a href="{{ route('tournaments.create') }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none transition">
                    {{ __('tournaments.index.create') }}
                </a>
            @endcan
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if(session('success'))
                <div class="p-4 bg-green-50 border-l-4 border-green-400 text-green-700 rounded-md">
                    {{ session('success') }}
                </div>
            @endif

            <!-- Filters -->
            <div class="bg-white p-4 sm:p-6 shadow sm:rounded-lg">
                <form method="GET" action="{{ route('tournaments.index') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-4 items-end">
                    <div class="lg:col-span-2">
                        <label for="search" class="block text-xs font-medium text-gray-700 uppercase mb-1">{{ __('tournaments.index.search_label') }}</label>
                        <input type="text" name="search" id="search" value="{{ request('search') }}" placeholder="{{ __('tournaments.index.search_placeholder') }}"
                               class="w-full text-sm border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                    </div>

                    <div>
                        <label for="club_id" class="block text-xs font-medium text-gray-700 uppercase mb-1">{{ __('tournaments.index.club_label') }}</label>
                        <select name="club_id" id="club_id" class="w-full text-sm border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">{{ __('tournaments.index.all_clubs') }}</option>
                            @foreach(($clubs ?? collect()) as $club)
                                <option value="{{ $club->id }}" @selected((string) request('club_id') === (string) $club->id)>
                                    {{ $club->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="status" class="block text-xs font-medium text-gray-700 uppercase mb-1">{{ __('tournaments.index.status_label') }}</label>
                        <select name="status" id="status" class="w-full text-sm border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">{{ __('tournaments.index.all_statuses') }}</option>
                            @foreach(App\Enums\TournamentStatus::cases() as $statusCase)
                                <option value="{{ $statusCase->value }}" @selected(request('status') === $statusCase->value)>
                                    {{ $statusCase->label() }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="pairing_type" class="block text-xs font-medium text-gray-700 uppercase mb-1">{{ __('tournaments.index.pairing_label') }}</label>
                        <select name="pairing_type" id="pairing_type" class="w-full text-sm border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">{{ __('tournaments.index.all_types') }}</option>
                            @foreach(App\Enums\PairingType::cases() as $pairingCase)
                                <option value="{{ $pairingCase->value }}" @selected(request('pairing_type') === $pairingCase->value)>
                                    {{ $pairingCase->label() }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="date_from" class="block text-xs font-medium text-gray-700 uppercase mb-1">{{ __('tournaments.index.date_from') }}</label>
                        <input type="date" name="date_from" id="date_from" value="{{ request('date_from') }}"
                               class="w-full text-sm border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                    </div>

                    <div>
                        <label for="date_to" class="block text-xs font-medium text-gray-700 uppercase mb-1">{{ __('tournaments.index.date_to') }}</label>
                        <input type="date" name="date_to" id="date_to" value="{{ request('date_to') }}"
                               class="w-full text-sm border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                    </div>

                    <div class="lg:col-span-6 flex space-x-2">
                        <button type="submit" class="py-2 px-4 bg-gray-800 text-white rounded-md text-sm font-medium hover:bg-gray-700 transition">
                            {{ __('common.search') }}
                        </button>
                        <a href="{{ route('tournaments.index') }}" class="py-2 px-3 bg-gray-200 text-gray-700 rounded-md text-sm font-medium hover:bg-gray-300 transition">
                            {{ __('common.reset') }}
                        </a>
                    </div>
                </form>
            </div>

            <!-- Tournaments list -->
            <div class="bg-white shadow sm:rounded-lg overflow-hidden">
                @if($tournaments->isEmpty())
                    <div class="p-8 text-center text-gray-500">
                        {{ __('tournaments.index.not_found') }}
                    </div>
                @else
                    <!-- Desktop table -->
                    <div class="hidden md:block overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-left text-sm">
                            <thead class="bg-gray-50 text-gray-500 uppercase tracking-wider text-xs">
                                <tr>
                                    <th scope="col" class="px-6 py-3">{{ __('tournaments.index.col_tournament') }}</th>
                                    <th scope="col" class="px-6 py-3">{{ __('tournaments.index.col_status') }}</th>
                                    <th scope="col" class="px-6 py-3">{{ __('tournaments.index.col_pairing') }}</th>
                                    <th scope="col" class="px-6 py-3">{{ __('tournaments.index.col_rating_mode') }}</th>
                                    <th scope="col" class="px-6 py-3">{{ __('tournaments.index.col_dates') }}</th>
                                    <th scope="col" class="px-6 py-3 text-center">{{ __('tournaments.index.col_rounds') }}</th>
                                    <th scope="col" class="px-6 py-3 text-center">{{ __('tournaments.index.col_participants') }}</th>
                                    <th scope="col" class="px-6 py-3">{{ __('tournaments.index.col_arbiter') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 bg-white">
                                @foreach($tournaments as $tournament)
                                    @php
                                        $statusClasses = match ($tournament->status) {
                                            App\Enums\TournamentStatus::Draft => 'bg-gray-100 text-gray-700',
                                            App\Enums\TournamentStatus::InProgress => 'bg-green-100 text-green-800',
                                            App\Enums\TournamentStatus::Completed => 'bg-blue-100 text-blue-800',
                                        };
                                    @endphp
                                    <tr class="hover:bg-gray-50 transition">
                                        <td class="px-6 py-4">
                                            <a href="{{ route('tournaments.show', $tournament) }}" class="font-medium text-indigo-600 hover:text-indigo-900 hover:underline">
                                                {{ $tournament->name }}
                                            </a>
                                            @if($tournament->location)
                                                <div class="text-xs text-gray-500 mt-0.5">{{ $tournament->location }}</div>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $statusClasses }}">
                                                {{ $tournament->status->label() }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-gray-600">
                                            {{ $tournament->pairing_type->label() }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-gray-600">
                                            {{ $tournament->rating_mode->shortLabel() }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-gray-600">
                                            {{ \App\Support\DateFormatter::format($tournament->start_date) }}
                                            @if($tournament->end_date)
                                                — {{ \App\Support\DateFormatter::format($tournament->end_date) }}
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-center text-gray-700">
                                            {{ $tournament->rounds_count }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-center text-gray-700">
                                            {{ $tournament->participants_count }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-gray-600">
                                            {{ $tournament->arbiter?->surname }} {{ $tournament->arbiter?->name }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <!-- Mobile cards -->
                    <div class="block md:hidden divide-y divide-gray-200">
                        @foreach($tournaments as $tournament)
                            @php
                                $statusClasses = match ($tournament->status) {
                                    App\Enums\TournamentStatus::Draft => 'bg-gray-100 text-gray-700',
                                    App\Enums\TournamentStatus::InProgress => 'bg-green-100 text-green-800',
                                    App\Enums\TournamentStatus::Completed => 'bg-blue-100 text-blue-800',
                                };
                            @endphp
                            <div class="p-4 space-y-2">
                                <div class="flex justify-between items-start gap-3">
                                    <a href="{{ route('tournaments.show', $tournament) }}" class="text-base font-semibold text-indigo-600 hover:underline">
                                        {{ $tournament->name }}
                                    </a>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium whitespace-nowrap {{ $statusClasses }}">
                                        {{ $tournament->status->label() }}
                                    </span>
                                </div>
                                <div class="text-xs text-gray-600">
                                    {{ $tournament->pairing_type->label() }} &bull; {{ $tournament->rating_mode->shortLabel() }}
                                </div>
                                <div class="text-xs text-gray-500">
                                    {{ \App\Support\DateFormatter::format($tournament->start_date) }}@if($tournament->end_date) — {{ \App\Support\DateFormatter::format($tournament->end_date) }}@endif
                                    &bull; {{ __('tournaments.index.mobile_meta', ['participants' => $tournament->participants_count, 'rounds' => $tournament->rounds_count]) }}
                                </div>
                                @if($tournament->location)
                                    <div class="text-xs text-gray-500">{{ $tournament->location }}</div>
                                @endif
                            </div>
                        @endforeach
                    </div>

                    @if($tournaments->hasPages())
                        <div class="p-4 border-t border-gray-200">
                            {{ $tournaments->links() }}
                        </div>
                    @endif
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
