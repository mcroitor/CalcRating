@php
    $isEdit = isset($tournament) && $tournament !== null;
    $locked = $isEdit && ! $tournament->isDraft();
    $selectedClubId = old('club_id', $isEdit ? $tournament->club_id : ($selectedClubId ?? null));
    $selectedPairingType = old('pairing_type', $isEdit ? $tournament->pairing_type->value : App\Enums\PairingType::Swiss->value);
    $selectedRatingMode = old('rating_mode', $isEdit ? $tournament->rating_mode->value : App\Enums\RatingMode::Average->value);
    $selectedArbiterId = old('arbiter_id', $isEdit ? $tournament->arbiter_id : Auth::id());
    $canChooseArbiter = $isEdit
        ? (Auth::user()->isAdmin() || ($tournament->club?->hasManagerUser(Auth::user()) ?? false))
        : true;
@endphp

<form method="POST" action="{{ $action }}" class="space-y-6">
    @csrf
    @if($isEdit)
        @method('PUT')
        <input type="hidden" name="version" value="{{ $tournament->version }}">
    @endif

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
        <!-- Club -->
        <div class="sm:col-span-2">
            @if(! $isEdit)
                <x-input-label for="club_id" :value="__('tournaments.form.club')" />
                <select id="club_id" name="club_id" required class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                    <option value="">{{ __('tournaments.form.select_club') }}</option>
                    @foreach(($clubs ?? collect()) as $club)
                        <option value="{{ $club->id }}" @selected((int) $selectedClubId === $club->id)>
                            {{ $club->name }}@if($club->code) ({{ $club->code }})@endif
                        </option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('club_id')" class="mt-2" />
            @else
                <x-input-label :value="__('tournaments.form.club')" />
                <div class="mt-1 text-sm font-medium text-gray-800">{{ $tournament->club?->name }}</div>
                <p class="text-xs text-gray-500 mt-1">{{ __('tournaments.form.club_locked') }}</p>
            @endif
        </div>

        <!-- Name -->
        <div class="sm:col-span-2">
            <x-input-label for="name" :value="__('tournaments.form.name')" />
            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full"
                          :value="old('name', $isEdit ? $tournament->name : '')" required autofocus />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <!-- Arbiter -->
        <div class="sm:col-span-2">
            @if($canChooseArbiter)
                <x-input-label for="arbiter_id" :value="__('tournaments.form.arbiter')" />
                <select id="arbiter_id" name="arbiter_id" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                    @if($isEdit)
                        @foreach($arbiters as $arbiter)
                            <option value="{{ $arbiter->id }}" @selected((int) $selectedArbiterId === $arbiter->id)>
                                {{ $arbiter->surname }} {{ $arbiter->name }} ({{ $arbiter->email }})
                            </option>
                        @endforeach
                    @else
                        @foreach($arbiters as $arbiter)
                            <option value="{{ $arbiter['id'] }}" @selected((int) $selectedArbiterId === (int) $arbiter['id'])>
                                {{ $arbiter['label'] }}
                            </option>
                        @endforeach
                    @endif
                </select>
                <x-input-error :messages="$errors->get('arbiter_id')" class="mt-2" />
            @else
                <x-input-label :value="__('tournaments.form.arbiter')" />
                <div class="mt-1 text-sm text-gray-700">
                    {{ $tournament->arbiter?->surname }} {{ $tournament->arbiter?->name }}
                </div>
            @endif
        </div>

        <!-- Pairing Type -->
        <div>
            <x-input-label for="pairing_type" :value="__('tournaments.form.pairing_type')" />
            <select id="pairing_type" name="pairing_type" @disabled($locked)
                    class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm disabled:bg-gray-100 disabled:text-gray-500">
                @foreach(App\Enums\PairingType::cases() as $pairingType)
                    <option value="{{ $pairingType->value }}" @selected($selectedPairingType === $pairingType->value)>
                        {{ $pairingType->label() }}
                    </option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('pairing_type')" class="mt-2" />
        </div>

        <!-- Rating Mode -->
        <div>
            <x-input-label for="rating_mode" :value="__('tournaments.form.rating_mode')" />
            <select id="rating_mode" name="rating_mode" @disabled($locked)
                    class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm disabled:bg-gray-100 disabled:text-gray-500">
                @foreach(App\Enums\RatingMode::cases() as $ratingMode)
                    <option value="{{ $ratingMode->value }}" @selected($selectedRatingMode === $ratingMode->value)>
                        {{ $ratingMode->label() }}
                    </option>
                @endforeach
            </select>
            <p class="text-xs text-gray-500 mt-1">
                {{ App\Enums\RatingMode::Average->description() }}
                {{ __('tournaments.form.rating_mode_alternative', ['description' => mb_strtolower(App\Enums\RatingMode::PerGame->description())]) }}
            </p>
            <x-input-error :messages="$errors->get('rating_mode')" class="mt-2" />
        </div>

        <!-- Rounds -->
        <div>
            <x-input-label for="rounds_count" :value="__('tournaments.form.rounds_count')" />
            <x-text-input id="rounds_count" name="rounds_count" type="number" min="1" max="50" class="mt-1 block w-full"
                          :value="old('rounds_count', $isEdit ? $tournament->rounds_count : 5)" required @disabled($locked) />
            <x-input-error :messages="$errors->get('rounds_count')" class="mt-2" />
        </div>

        <!-- Games per round -->
        <div>
            <x-input-label for="games_per_round" :value="__('tournaments.form.games_per_round')" />
            <x-text-input id="games_per_round" name="games_per_round" type="number" min="1" max="10" class="mt-1 block w-full"
                          :value="old('games_per_round', $isEdit ? $tournament->games_per_round : 1)" required @disabled($locked) />
            <x-input-error :messages="$errors->get('games_per_round')" class="mt-2" />
        </div>

        <!-- Start date -->
        <div>
            <x-input-label for="start_date" :value="__('tournaments.form.start_date')" />
            <x-text-input id="start_date" name="start_date" type="date" class="mt-1 block w-full"
                          :value="old('start_date', $isEdit ? $tournament->start_date->format('Y-m-d') : now()->addDays(7)->format('Y-m-d'))" required />
            <x-input-error :messages="$errors->get('start_date')" class="mt-2" />
        </div>

        <!-- End date -->
        <div>
            <x-input-label for="end_date" :value="__('tournaments.form.end_date')" />
            <x-text-input id="end_date" name="end_date" type="date" class="mt-1 block w-full"
                          :value="old('end_date', $isEdit ? $tournament->end_date?->format('Y-m-d') : now()->addDays(9)->format('Y-m-d'))" />
            <x-input-error :messages="$errors->get('end_date')" class="mt-2" />
        </div>

        <!-- Location -->
        <div class="sm:col-span-2">
            <x-input-label for="location" :value="__('tournaments.form.location')" />
            <x-text-input id="location" name="location" type="text" class="mt-1 block w-full"
                          :value="old('location', $isEdit ? $tournament->location : '')" />
            <x-input-error :messages="$errors->get('location')" class="mt-2" />
        </div>

        <!-- Notes -->
        <div class="sm:col-span-2">
            <x-input-label for="notes" :value="__('tournaments.form.notes')" />
            <textarea id="notes" name="notes" rows="4"
                      class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">{{ old('notes', $isEdit ? $tournament->notes : '') }}</textarea>
            <x-input-error :messages="$errors->get('notes')" class="mt-2" />
        </div>
    </div>

    @if($locked)
        <div class="p-4 bg-amber-50 border-l-4 border-amber-400 text-amber-800 rounded-md text-sm">
            {{ __('tournaments.form.locked') }}
        </div>
    @endif

    <div class="flex items-center justify-end space-x-3 pt-4 border-t border-gray-200">
        <a href="{{ $isEdit ? route('tournaments.show', $tournament) : route('tournaments.index') }}"
           class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300 text-sm font-medium transition">
            {{ __('common.cancel') }}
        </a>
        <x-primary-button>
            {{ $submitLabel }}
        </x-primary-button>
    </div>
</form>

@if(! $isEdit && isset($clubArbitersMap))
    <script>
        (function () {
            const clubSelect = document.getElementById('club_id');
            const arbiterSelect = document.getElementById('arbiter_id');
            if (!clubSelect || !arbiterSelect) return;
            const map = @json($clubArbitersMap);
            clubSelect.addEventListener('change', function () {
                const list = map[this.value] || [];
                const prev = arbiterSelect.value;
                arbiterSelect.innerHTML = '';
                list.forEach(function (item) {
                    const opt = document.createElement('option');
                    opt.value = String(item.id);
                    opt.textContent = item.label;
                    if (String(item.id) === prev) opt.selected = true;
                    arbiterSelect.appendChild(opt);
                });
            });
        })();
    </script>
@endif
