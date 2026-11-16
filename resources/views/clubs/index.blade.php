<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('clubs.index.title') }}
            </h2>

            @can('create', \App\Models\Club::class)
                <a href="{{ route('clubs.create') }}"
                   class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 transition">
                    {{ __('clubs.index.create') }}
                </a>
            @endcan
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('success'))
                <div class="p-4 bg-green-50 border-l-4 border-green-400 text-green-700 rounded-md">{{ session('success') }}</div>
            @endif
            @if (session('error'))
                <div class="p-4 bg-red-50 border-l-4 border-red-400 text-red-700 rounded-md">{{ session('error') }}</div>
            @endif

            <div class="bg-white shadow sm:rounded-lg p-4">
                <form method="GET" action="{{ route('clubs.index') }}" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                    <div class="md:col-span-2">
                        <label for="search" class="block text-xs font-semibold uppercase tracking-wider text-gray-500 mb-1">
                            {{ __('clubs.index.search_label') }}
                        </label>
                        <input id="search" name="search" type="search" value="{{ request('search') }}"
                               placeholder="{{ __('clubs.index.search_placeholder') }}"
                               class="w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>

                    <div>
                        <label for="status" class="block text-xs font-semibold uppercase tracking-wider text-gray-500 mb-1">
                            {{ __('clubs.index.status_label') }}
                        </label>
                        @php $currentStatus = request('status', 'active'); @endphp
                        <select id="status" name="status" class="w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="all" @selected($currentStatus === 'all')>{{ __('clubs.index.all_statuses') }}</option>
                            @foreach(\App\Enums\ClubStatus::cases() as $statusCase)
                                <option value="{{ $statusCase->value }}" @selected($currentStatus === $statusCase->value)>
                                    {{ $statusCase->label() }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="flex gap-2">
                        <button type="submit" class="px-4 py-2 bg-gray-800 text-white rounded-md text-sm font-medium hover:bg-gray-700 transition">
                            {{ __('common.apply') }}
                        </button>
                        <a href="{{ route('clubs.index') }}" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md text-sm font-medium hover:bg-gray-300 transition">
                            {{ __('common.reset') }}
                        </a>
                    </div>
                </form>
            </div>

            @if($clubs->isEmpty())
                <div class="bg-white shadow sm:rounded-lg p-8 text-center text-gray-500">
                    {{ __('clubs.index.not_found') }}
                </div>
            @else
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    @foreach($clubs as $club)
                        <div class="bg-white shadow sm:rounded-lg p-5 flex flex-col justify-between gap-4">
                            <div class="flex items-start gap-4">
                                @if($club->logoUrl())
                                    <img src="{{ $club->logoUrl() }}" alt="{{ $club->name }}"
                                         class="w-14 h-14 rounded-lg object-cover border border-gray-200 shrink-0">
                                @else
                                    <div class="w-14 h-14 rounded-lg bg-indigo-50 text-indigo-700 font-bold text-lg flex items-center justify-center border border-indigo-100 shrink-0">
                                        {{ $club->initials() }}
                                    </div>
                                @endif

                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <a href="{{ route('clubs.show', $club) }}" class="font-semibold text-base text-gray-900 hover:text-indigo-600 truncate">
                                            {{ $club->name }}
                                        </a>
                                        @if($club->code)
                                            <span class="px-2 py-0.5 text-xs font-mono bg-gray-100 text-gray-700 rounded">{{ $club->code }}</span>
                                        @endif
                                    </div>

                                    <div class="mt-1 flex items-center gap-2 text-xs text-gray-500">
                                        @if($club->city)
                                            <span>{{ $club->city }}</span>
                                        @endif
                                        @if($club->isArchived())
                                            <span class="px-2 py-0.5 rounded-full bg-gray-200 text-gray-700 font-medium">
                                                {{ $club->status->label() }}
                                            </span>
                                        @endif
                                    </div>

                                    @if($club->description)
                                        <p class="mt-2 text-sm text-gray-600 line-clamp-2">{{ $club->description }}</p>
                                    @endif
                                </div>
                            </div>

                            <div class="pt-3 border-t border-gray-100 flex items-center justify-between text-xs text-gray-500">
                                <div class="flex items-center gap-3">
                                    <span>{{ trans_choice('clubs.index.members_count', $club->members_count, ['count' => $club->members_count]) }}</span>
                                    <span>&bull;</span>
                                    <span>{{ trans_choice('clubs.index.tournaments_count', $club->tournaments_count, ['count' => $club->tournaments_count]) }}</span>
                                </div>
                                <a href="{{ route('clubs.show', $club) }}" class="text-indigo-600 font-medium hover:underline">
                                    {{ __('clubs.index.view') }}
                                </a>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div>
                    {{ $clubs->links() }}
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
