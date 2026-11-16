<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <div>
                <h2 class="font-semibold text-2xl text-gray-800 leading-tight">
                    {{ $player->full_name }}
                </h2>
                <div class="text-sm text-gray-500 mt-1">
                    {{ __('players.show.subtitle', ['id' => $player->id, 'title' => $player->title->label()]) }}
                </div>
            </div>

            <div class="flex items-center space-x-3">
                <a href="{{ route('players.index') }}" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md text-sm font-medium hover:bg-gray-300 transition">
                    {{ __('players.show.back') }}
                </a>

                @can('update', $player)
                    <a href="{{ route('players.edit', $player) }}" class="px-4 py-2 bg-indigo-600 text-white rounded-md text-sm font-medium hover:bg-indigo-700 transition">
                        {{ __('players.show.edit') }}
                    </a>
                @endcan

                @can('delete', $player)
                    <form method="POST" action="{{ route('players.destroy', $player) }}" onsubmit="return confirm('{{ __('players.show.delete_confirm') }}');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-md text-sm font-medium hover:bg-red-700 transition">
                            {{ __('players.show.delete') }}
                        </button>
                    </form>
                @else
                    @if(auth()->user()?->hasRole(['arbiter', 'admin']))
                        <button type="button" disabled title="{{ __('players.show.delete_blocked_title') }}" class="px-4 py-2 bg-gray-300 text-gray-500 rounded-md text-sm font-medium cursor-not-allowed">
                            {{ __('players.show.delete_blocked') }}
                        </button>
                    @endif
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

            <!-- Main Info Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                <!-- Rating Card -->
                <div class="bg-white p-6 shadow sm:rounded-lg flex flex-col justify-center items-center text-center">
                    <span class="text-xs uppercase font-semibold text-gray-500 tracking-wider">{{ __('players.show.current_rating') }}</span>
                    <span class="text-4xl font-extrabold text-indigo-600 mt-2">{{ $player->current_rating }}</span>
                    <span class="text-xs text-gray-400 mt-1">{{ __('players.show.elo_formula') }}</span>
                </div>

                <!-- Title Card -->
                <div class="bg-white p-6 shadow sm:rounded-lg flex flex-col justify-center items-center text-center">
                    <span class="text-xs uppercase font-semibold text-gray-500 tracking-wider">{{ __('players.show.category') }}</span>
                    <span class="text-xl font-bold text-gray-800 mt-2">{{ $player->title->label() }}</span>
                    <span class="text-xs text-gray-400 mt-1">({{ $player->title->value }})</span>
                </div>

                <!-- Age Card -->
                <div class="bg-white p-6 shadow sm:rounded-lg flex flex-col justify-center items-center text-center">
                    <span class="text-xs uppercase font-semibold text-gray-500 tracking-wider">{{ __('players.show.age_birth') }}</span>
                    <span class="text-2xl font-bold text-gray-800 mt-2">{{ trans_choice('common.years_old', $player->age, ['age' => $player->age]) }}</span>
                    <span class="text-xs text-gray-500 mt-1">{{ \App\Support\DateFormatter::format($player->birth_date) }}</span>
                </div>

                <!-- Status Card -->
                <div class="bg-white p-6 shadow sm:rounded-lg flex flex-col justify-center items-center text-center">
                    <span class="text-xs uppercase font-semibold text-gray-500 tracking-wider">{{ __('players.show.history_count') }}</span>
                    <span class="text-2xl font-bold text-gray-800 mt-2">{{ $player->ratings->count() }}</span>
                    <span class="text-xs text-gray-400 mt-1">{{ __('players.show.chart_points') }}</span>
                </div>
            </div>

            <!-- Clubs -->
            <div class="bg-white p-6 shadow sm:rounded-lg">
                <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wider mb-3">{{ __('players.show.clubs') }}</h3>
                @if($player->clubs->isEmpty())
                    <p class="text-sm text-gray-500">{{ __('players.show.no_clubs') }}</p>
                @else
                    <div class="flex flex-wrap gap-2">
                        @foreach($player->clubs as $club)
                            <a href="{{ route('clubs.show', $club) }}"
                               class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium bg-indigo-50 text-indigo-700 hover:bg-indigo-100 transition">
                                <span>{{ $club->name }}</span>
                                @if($club->code)
                                    <span class="font-mono text-indigo-500">({{ $club->code }})</span>
                                @endif
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>

            <!-- Notes if present -->
            @if($player->notes)
                <div class="bg-white p-6 shadow sm:rounded-lg">
                    <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wider mb-2">{{ __('players.show.notes') }}</h3>
                    <p class="text-gray-700 whitespace-pre-line text-sm">{{ $player->notes }}</p>
                </div>
            @endif

            <!-- Interactive Chart.js Section -->
            <div class="bg-white p-6 shadow sm:rounded-lg">
                <h3 class="text-lg font-bold text-gray-900 mb-4">{{ __('players.show.chart_title') }}</h3>
                <div class="h-72 w-full">
                    <canvas id="ratingChart"></canvas>
                </div>
            </div>

            <!-- Rating History Table -->
            <div class="bg-white shadow sm:rounded-lg overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-bold text-gray-900">{{ __('players.show.history_title') }}</h3>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-left text-sm">
                        <thead class="bg-gray-50 text-gray-500 uppercase tracking-wider text-xs">
                            <tr>
                                <th scope="col" class="px-6 py-3">{{ __('players.show.col_recorded') }}</th>
                                <th scope="col" class="px-6 py-3">{{ __('players.show.col_tournament') }}</th>
                                <th scope="col" class="px-6 py-3">{{ __('players.show.col_new_rating') }}</th>
                                <th scope="col" class="px-6 py-3">{{ __('players.show.col_change') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            @php
                                $prevRating = null;
                            @endphp
                            @forelse($player->ratings as $rating)
                                @php
                                    $diff = $prevRating !== null ? ($rating->value - $prevRating) : 0;
                                    $prevRating = $rating->value;
                                @endphp
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-gray-700">
                                        {{ \App\Support\DateFormatter::format($rating->created_at, true) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-gray-600">
                                        @if($rating->tournament_id)
                                            <span class="font-medium text-indigo-600">{{ __('players.show.tournament', ['id' => $rating->tournament_id]) }}</span>
                                        @else
                                            <span class="text-gray-400 italic">{{ __('players.show.initial_rating') }}</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap font-bold text-gray-900">
                                        {{ $rating->value }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold">
                                        @if($diff > 0)
                                            <span class="text-green-600">+{{ $diff }}</span>
                                        @elseif($diff < 0)
                                            <span class="text-red-600">{{ $diff }}</span>
                                        @else
                                            <span class="text-gray-400">0</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-6 py-4 text-center text-gray-500">
                                        {{ __('players.show.no_history') }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Chart.js Init Script -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const labels = @json($player->ratings->map(fn($r) => \App\Support\DateFormatter::format($r->created_at))->values());
            const data = @json($player->ratings->pluck('value')->values());

            const canvas = document.getElementById('ratingChart');
            if (canvas && window.initRatingChart) {
                window.initRatingChart(canvas, labels, data);
            }
        });
    </script>
</x-app-layout>
