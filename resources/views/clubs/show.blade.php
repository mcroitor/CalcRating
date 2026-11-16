<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div class="flex items-center gap-3">
                @if($club->logoUrl())
                    <img src="{{ $club->logoUrl() }}" alt="{{ $club->name }}" class="w-12 h-12 rounded-lg object-cover border border-gray-200">
                @else
                    <div class="w-12 h-12 rounded-lg bg-indigo-50 text-indigo-700 font-bold text-base flex items-center justify-center border border-indigo-100">
                        {{ $club->initials() }}
                    </div>
                @endif
                <div>
                    <div class="flex items-center gap-2 flex-wrap">
                        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $club->name }}</h2>
                        @if($club->code)
                            <span class="px-2 py-0.5 text-xs font-mono bg-gray-100 text-gray-700 rounded">{{ $club->code }}</span>
                        @endif
                        <span class="px-2.5 py-0.5 rounded-full text-xs font-medium {{ $club->isActive() ? 'bg-green-100 text-green-800' : 'bg-gray-200 text-gray-700' }}">
                            {{ $club->status->label() }}
                        </span>
                    </div>
                    <a href="{{ route('clubs.index') }}" class="text-xs text-gray-500 hover:text-gray-800">
                        {{ __('clubs.show.back') }}
                    </a>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                @can('createTournament', $club)
                    <a href="{{ route('tournaments.create', ['club_id' => $club->id]) }}"
                       class="px-3 py-1.5 bg-indigo-600 text-white rounded-md text-xs font-medium hover:bg-indigo-700 transition">
                        {{ __('clubs.show.create_tournament') }}
                    </a>
                @endcan

                @can('update', $club)
                    <a href="{{ route('clubs.edit', $club) }}"
                       class="px-3 py-1.5 bg-gray-200 text-gray-800 rounded-md text-xs font-medium hover:bg-gray-300 transition">
                        {{ __('clubs.show.edit') }}
                    </a>
                @endcan

                @if($club->isActive())
                    @can('archive', $club)
                        <form method="POST" action="{{ route('clubs.archive', $club) }}"
                              onsubmit="return confirm('{{ __('clubs.show.archive_confirm') }}');">
                            @csrf
                            <input type="hidden" name="version" value="{{ $club->version }}">
                            <button type="submit" class="px-3 py-1.5 bg-amber-600 text-white rounded-md text-xs font-medium hover:bg-amber-700 transition">
                                {{ __('clubs.show.archive') }}
                            </button>
                        </form>
                    @endcan
                @else
                    @can('restore', $club)
                        <form method="POST" action="{{ route('clubs.restore', $club) }}">
                            @csrf
                            <input type="hidden" name="version" value="{{ $club->version }}">
                            <button type="submit" class="px-3 py-1.5 bg-green-600 text-white rounded-md text-xs font-medium hover:bg-green-700 transition">
                                {{ __('clubs.show.restore') }}
                            </button>
                        </form>
                    @endcan
                @endif

                @can('delete', $club)
                    @if($club->tournaments->isEmpty())
                        <form method="POST" action="{{ route('clubs.destroy', $club) }}"
                              onsubmit="return confirm('{{ __('clubs.show.delete_confirm') }}');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="px-3 py-1.5 bg-red-600 text-white rounded-md text-xs font-medium hover:bg-red-700 transition">
                                {{ __('clubs.show.delete') }}
                            </button>
                        </form>
                    @endif
                @endcan
            </div>
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

            @if($club->isArchived())
                <div class="p-4 bg-gray-100 border-l-4 border-gray-400 text-gray-700 rounded-md text-sm">
                    {{ __('clubs.show.archived_notice') }}
                </div>
            @endif

            <div class="bg-white shadow sm:rounded-lg p-6 space-y-3">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm text-gray-700">
                    @if($club->city || $club->address)
                        <div>
                            <span class="font-semibold text-gray-500">{{ __('clubs.show.location') }}:</span>
                            <span>{{ trim(implode(', ', array_filter([$club->city, $club->address]))) }}</span>
                        </div>
                    @endif
                    @if($club->website)
                        <div>
                            <span class="font-semibold text-gray-500">{{ __('clubs.show.website') }}:</span>
                            <a href="{{ $club->website }}" target="_blank" rel="noopener noreferrer" class="text-indigo-600 hover:underline">
                                {{ $club->website }}
                            </a>
                        </div>
                    @endif
                </div>
                @if($club->description)
                    <p class="text-sm text-gray-700 whitespace-pre-line">{{ $club->description }}</p>
                @endif
            </div>

            {{-- Club Tournaments --}}
            <div class="bg-white shadow sm:rounded-lg p-6 space-y-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-900">{{ __('clubs.show.tournaments_title') }} ({{ $club->tournaments->count() }})</h3>
                </div>

                @if($club->tournaments->isEmpty())
                    <p class="text-sm text-gray-500">{{ __('clubs.show.no_tournaments') }}</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2 text-left font-medium text-gray-500">{{ __('tournaments.index.col_tournament') }}</th>
                                    <th class="px-4 py-2 text-left font-medium text-gray-500">{{ __('tournaments.index.col_status') }}</th>
                                    <th class="px-4 py-2 text-left font-medium text-gray-500">{{ __('tournaments.index.col_dates') }}</th>
                                    <th class="px-4 py-2 text-left font-medium text-gray-500">{{ __('tournaments.index.col_participants') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach($club->tournaments as $tournament)
                                    <tr>
                                        <td class="px-4 py-2 font-medium">
                                            <a href="{{ route('tournaments.show', $tournament) }}" class="text-indigo-600 hover:underline">
                                                {{ $tournament->name }}
                                            </a>
                                        </td>
                                        <td class="px-4 py-2">{{ $tournament->status->label() }}</td>
                                        <td class="px-4 py-2 text-gray-600">
                                            {{ \App\Support\DateFormatter::format($tournament->start_date) }}
                                            @if($tournament->end_date)
                                                — {{ \App\Support\DateFormatter::format($tournament->end_date) }}
                                            @endif
                                        </td>
                                        <td class="px-4 py-2 text-gray-600">{{ $tournament->participants_count }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                {{-- Club Members --}}
                <div class="bg-white shadow sm:rounded-lg p-6 space-y-4">
                    <h3 class="text-lg font-semibold text-gray-900">{{ __('clubs.show.members_title') }} ({{ $club->members->count() }})</h3>

                    @can('manageMembers', $club)
                        <form method="GET" action="{{ route('clubs.show', $club) }}" class="flex gap-2">
                            <input type="search" name="member_search" value="{{ request('member_search') }}"
                                   placeholder="{{ __('clubs.show.member_search_placeholder') }}"
                                   class="flex-1 rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <button type="submit" class="px-3 py-1.5 bg-gray-800 text-white rounded-md text-xs font-medium">
                                {{ __('common.search') }}
                            </button>
                        </form>

                        @if($availablePlayers->isEmpty())
                            <p class="text-xs text-gray-500">{{ __('clubs.show.no_players_found') }}</p>
                        @else
                            <div class="divide-y divide-gray-100 border border-gray-200 rounded-md">
                                @foreach($availablePlayers as $candidate)
                                    <div class="px-3 py-2 flex items-center justify-between text-sm">
                                        <span>{{ $candidate->full_name }} ({{ $candidate->current_rating }})</span>
                                        <form method="POST" action="{{ route('clubs.members.store', $club) }}">
                                            @csrf
                                            <input type="hidden" name="player_id" value="{{ $candidate->id }}">
                                            <button type="submit" class="text-xs font-medium text-indigo-600 hover:underline">
                                                {{ __('clubs.show.add_member_btn') }}
                                            </button>
                                        </form>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    @endcan

                    @if($club->members->isEmpty())
                        <p class="text-sm text-gray-500">{{ __('clubs.show.no_members') }}</p>
                    @else
                        <div class="divide-y divide-gray-100">
                            @foreach($club->members as $member)
                                <div class="py-2 flex items-center justify-between text-sm">
                                    <div>
                                        <a href="{{ route('players.show', $member) }}" class="font-medium text-gray-900 hover:text-indigo-600">
                                            {{ $member->full_name }}
                                        </a>
                                        <span class="text-xs text-gray-500 ml-2">{{ $member->current_rating }}</span>
                                    </div>
                                    @can('manageMembers', $club)
                                        <form method="POST" action="{{ route('clubs.members.destroy', [$club, $member]) }}"
                                              onsubmit="return confirm('{{ __('clubs.show.remove_member_confirm') }}');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-xs text-red-600 hover:underline">
                                                {{ __('clubs.show.remove_member') }}
                                            </button>
                                        </form>
                                    @endcan
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- Club Staff --}}
                <div class="bg-white shadow sm:rounded-lg p-6 space-y-4">
                    <h3 class="text-lg font-semibold text-gray-900">{{ __('clubs.show.staff_title') }} ({{ $club->staff->count() }})</h3>

                    @if(Gate::allows('manageArbiters', $club) || Gate::allows('manageManagers', $club))
                        @if($availableStaffUsers->isNotEmpty())
                            <form method="POST" action="{{ route('clubs.staff.store', $club) }}" class="flex flex-wrap gap-2 items-center">
                                @csrf
                                <select name="user_id" required class="flex-1 min-w-[180px] rounded-md border-gray-300 text-sm">
                                    <option value="">{{ __('clubs.show.select_user') }}</option>
                                    @foreach($availableStaffUsers as $candidateUser)
                                        <option value="{{ $candidateUser->id }}">
                                            {{ $candidateUser->surname }} {{ $candidateUser->name }} ({{ $candidateUser->email }})
                                        </option>
                                    @endforeach
                                </select>

                                <select name="role" required class="rounded-md border-gray-300 text-sm">
                                    @can('manageManagers', $club)
                                        <option value="{{ \App\Enums\ClubStaffRole::Manager->value }}">{{ \App\Enums\ClubStaffRole::Manager->label() }}</option>
                                    @endcan
                                    <option value="{{ \App\Enums\ClubStaffRole::Arbiter->value }}">{{ \App\Enums\ClubStaffRole::Arbiter->label() }}</option>
                                </select>

                                <button type="submit" class="px-3 py-1.5 bg-indigo-600 text-white rounded-md text-xs font-medium hover:bg-indigo-700">
                                    {{ __('clubs.show.assign_btn') }}
                                </button>
                            </form>
                        @endif
                    @endif

                    @if($club->staff->isEmpty())
                        <p class="text-sm text-gray-500">{{ __('clubs.show.no_staff') }}</p>
                    @else
                        <div class="divide-y divide-gray-100">
                            @foreach($club->staff as $staffUser)
                                @php $staffRole = \App\Enums\ClubStaffRole::tryFrom($staffUser->pivot->role); @endphp
                                <div class="py-2 flex items-center justify-between text-sm">
                                    <div>
                                        <span class="font-medium text-gray-900">{{ $staffUser->surname }} {{ $staffUser->name }}</span>
                                        <span class="ml-2 px-2 py-0.5 text-xs rounded-full {{ $staffRole === \App\Enums\ClubStaffRole::Manager ? 'bg-indigo-100 text-indigo-800' : 'bg-gray-100 text-gray-700' }}">
                                            {{ $staffRole?->label() ?? $staffUser->pivot->role }}
                                        </span>
                                    </div>
                                    @if(($staffRole === \App\Enums\ClubStaffRole::Manager && Gate::allows('manageManagers', $club))
                                        || ($staffRole !== \App\Enums\ClubStaffRole::Manager && Gate::allows('manageArbiters', $club)))
                                        <form method="POST" action="{{ route('clubs.staff.destroy', [$club, $staffUser]) }}"
                                              onsubmit="return confirm('{{ __('clubs.show.remove_staff_confirm') }}');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-xs text-red-600 hover:underline">
                                                {{ __('clubs.show.remove_staff') }}
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
