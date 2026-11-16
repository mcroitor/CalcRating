<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('players.index.title') }}
            </h2>
            <div class="flex flex-wrap items-center gap-2">
                @can('create', App\Models\Player::class)
                    <a href="{{ route('players.import') }}" class="inline-flex items-center px-3 py-2 bg-white border border-gray-300 rounded-md font-medium text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-50 focus:outline-none transition">
                        {{ __('players.index.import') }}
                    </a>

                    <!-- Export Dropdown -->
                    <div x-data="{ open: false }" class="relative">
                        <button @click="open = ! open" class="inline-flex items-center px-3 py-2 bg-white border border-gray-300 rounded-md font-medium text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-50 focus:outline-none transition">
                            {{ __('players.index.export') }}
                        </button>
                        <div x-show="open" @click.away="open = false" class="origin-top-right absolute right-0 mt-2 w-44 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 z-20 py-1" style="display: none;">
                            <a href="{{ route('players.export', array_merge(['format' => 'xlsx'], request()->query())) }}" class="block px-4 py-2 text-xs text-gray-700 hover:bg-gray-100">
                                {{ __('players.index.export_xlsx') }}
                            </a>
                            <a href="{{ route('players.export', array_merge(['format' => 'pdf'], request()->query())) }}" class="block px-4 py-2 text-xs text-gray-700 hover:bg-gray-100">
                                {{ __('players.index.export_pdf') }}
                            </a>
                            <a href="{{ route('players.export', array_merge(['format' => 'html'], request()->query())) }}" target="_blank" class="block px-4 py-2 text-xs text-gray-700 hover:bg-gray-100">
                                {{ __('players.index.export_html') }}
                            </a>
                        </div>
                    </div>

                    <a href="{{ route('players.create') }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                        {{ __('players.index.add') }}
                    </a>
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

            <!-- Search and Filter Form -->
            <div class="bg-white p-4 sm:p-6 shadow sm:rounded-lg">
                <form method="GET" action="{{ route('players.index') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-4 items-end">
                    <div class="lg:col-span-2">
                        <label for="search" class="block text-xs font-medium text-gray-700 uppercase mb-1">{{ __('players.index.search_label') }}</label>
                        <input type="text" name="search" id="search" value="{{ request('search') }}" placeholder="{{ __('players.index.search_placeholder') }}" class="w-full text-sm border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                    </div>

                    <div>
                        <label for="title" class="block text-xs font-medium text-gray-700 uppercase mb-1">{{ __('players.index.title_label') }}</label>
                        <select name="title" id="title" class="w-full text-sm border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">{{ __('players.index.all_titles') }}</option>
                            @foreach(App\Enums\ChessTitle::cases() as $titleCase)
                                <option value="{{ $titleCase->value }}" @selected(request('title') === $titleCase->value)>
                                    {{ $titleCase->label() }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="club_id" class="block text-xs font-medium text-gray-700 uppercase mb-1">{{ __('players.index.club_label') }}</label>
                        <select name="club_id" id="club_id" class="w-full text-sm border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">{{ __('players.index.all_clubs') }}</option>
                            <option value="none" @selected(request('club_id') === 'none')>{{ __('players.index.no_club') }}</option>
                            @foreach(($clubs ?? collect()) as $club)
                                <option value="{{ $club->id }}" @selected((string) request('club_id') === (string) $club->id)>
                                    {{ $club->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="min_rating" class="block text-xs font-medium text-gray-700 uppercase mb-1">{{ __('players.index.min_rating') }}</label>
                        <input type="number" name="min_rating" id="min_rating" value="{{ request('min_rating') }}" placeholder="0" class="w-full text-sm border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                    </div>

                    <div class="flex space-x-2">
                        <button type="submit" class="w-full py-2 px-4 bg-gray-800 text-white rounded-md text-sm font-medium hover:bg-gray-700 transition">
                            {{ __('common.search') }}
                        </button>
                        <a href="{{ route('players.index') }}" class="py-2 px-3 bg-gray-200 text-gray-700 rounded-md text-sm font-medium hover:bg-gray-300 transition text-center">
                            {{ __('common.reset') }}
                        </a>
                    </div>
                </form>
            </div>

            <!-- Players Table (Desktop) & Cards (Mobile) -->
            <div class="bg-white shadow sm:rounded-lg overflow-hidden">
                @if($players->isEmpty())
                    <div class="p-8 text-center text-gray-500">
                        {{ __('players.index.not_found') }}
                    </div>
                @else
                    <!-- Desktop Table -->
                    <div class="hidden md:block overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-left text-sm">
                            <thead class="bg-gray-50 text-gray-500 uppercase tracking-wider text-xs">
                                <tr>
                                    <th scope="col" class="px-6 py-3">{{ __('players.index.col_number') }}</th>
                                    <th scope="col" class="px-6 py-3">{{ __('players.index.col_name') }}</th>
                                    <th scope="col" class="px-6 py-3">{{ __('players.index.col_birth_age') }}</th>
                                    <th scope="col" class="px-6 py-3">{{ __('players.index.col_title') }}</th>
                                    <th scope="col" class="px-6 py-3">{{ __('players.index.col_rating') }}</th>
                                    <th scope="col" class="px-6 py-3 text-right">{{ __('players.index.col_actions') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 bg-white">
                                @foreach($players as $player)
                                    <tr class="hover:bg-gray-50 transition">
                                        <td class="px-6 py-4 whitespace-nowrap text-gray-500 font-mono">
                                            #{{ $player->id }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap font-medium text-gray-900">
                                            <a href="{{ route('players.show', $player) }}" class="text-indigo-600 hover:text-indigo-900 hover:underline">
                                                {{ $player->full_name }}
                                            </a>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-gray-600">
                                            {{ \App\Support\DateFormatter::format($player->birth_date) }} ({{ trans_choice('common.years_old', $player->age, ['age' => $player->age]) }})
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                                {{ $player->title->label() }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap font-bold text-gray-900">
                                            {{ $player->current_rating }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm space-x-2">
                                            <a href="{{ route('players.show', $player) }}" class="text-gray-600 hover:text-gray-900">{{ __('players.index.card') }}</a>
                                            @can('update', $player)
                                                <a href="{{ route('players.edit', $player) }}" class="text-indigo-600 hover:text-indigo-900">{{ __('players.index.edit_action') }}</a>
                                            @endcan
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <!-- Mobile Cards -->
                    <div class="block md:hidden divide-y divide-gray-200">
                        @foreach($players as $player)
                            <div class="p-4 space-y-2">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <a href="{{ route('players.show', $player) }}" class="text-base font-semibold text-indigo-600 hover:underline">
                                            {{ $player->full_name }}
                                        </a>
                                        <div class="text-xs text-gray-500">ID: #{{ $player->id }}</div>
                                    </div>
                                    <div class="text-right">
                                        <span class="text-lg font-bold text-gray-900">{{ $player->current_rating }}</span>
                                        <div class="text-xs text-gray-500">{{ __('players.index.rating') }}</div>
                                    </div>
                                </div>
                                <div class="flex justify-between items-center text-xs text-gray-600">
                                    <span>{{ \App\Support\DateFormatter::format($player->birth_date) }} ({{ trans_choice('common.years_old', $player->age, ['age' => $player->age]) }})</span>
                                    <span class="px-2 py-0.5 rounded bg-gray-100 text-gray-800 font-medium">
                                        {{ $player->title->label() }}
                                    </span>
                                </div>
                                <div class="pt-2 flex justify-end space-x-3 text-sm">
                                    <a href="{{ route('players.show', $player) }}" class="text-gray-600 hover:underline">{{ __('players.index.card') }}</a>
                                    @can('update', $player)
                                        <a href="{{ route('players.edit', $player) }}" class="text-indigo-600 hover:underline">{{ __('players.index.edit_action') }}</a>
                                    @endcan
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <!-- Pagination -->
                    @if($players->hasPages())
                        <div class="p-4 border-t border-gray-200">
                            {{ $players->links() }}
                        </div>
                    @endif
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
