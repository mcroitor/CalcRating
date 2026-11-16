<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('players.edit.title', ['id' => $player->id]) }}
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow sm:rounded-lg p-6">
                <form method="POST" action="{{ route('players.update', $player) }}" class="space-y-6">
                    @csrf
                    @method('PUT')

                    <!-- Version for Optimistic Locking -->
                    <input type="hidden" name="version" value="{{ $player->version }}" />

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                        <!-- First Name -->
                        <div>
                            <x-input-label for="first_name" :value="__('players.fields.first_name') . ' *'" />
                            <x-text-input id="first_name" name="first_name" type="text" class="mt-1 block w-full" :value="old('first_name', $player->first_name)" required autofocus />
                            <x-input-error :messages="$errors->get('first_name')" class="mt-2" />
                        </div>

                        <!-- Last Name -->
                        <div>
                            <x-input-label for="last_name" :value="__('players.fields.last_name') . ' *'" />
                            <x-text-input id="last_name" name="last_name" type="text" class="mt-1 block w-full" :value="old('last_name', $player->last_name)" required />
                            <x-input-error :messages="$errors->get('last_name')" class="mt-2" />
                        </div>

                        <!-- Birth Date -->
                        <div>
                            <x-input-label for="birth_date" :value="__('players.fields.birth_date') . ' *'" />
                            <x-text-input id="birth_date" name="birth_date" type="date" class="mt-1 block w-full" :value="old('birth_date', $player->birth_date->format('Y-m-d'))" required />
                            <x-input-error :messages="$errors->get('birth_date')" class="mt-2" />
                        </div>

                        <!-- Title -->
                        <div>
                            <x-input-label for="title" :value="__('players.fields.title')" />
                            <select id="title" name="title" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                @foreach(App\Enums\ChessTitle::cases() as $titleCase)
                                    <option value="{{ $titleCase->value }}" @selected(old('title', $player->title->value) === $titleCase->value)>
                                        {{ $titleCase->label() }}
                                    </option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('title')" class="mt-2" />
                        </div>

                        <!-- Current Rating (Read-only) -->
                        <div class="sm:col-span-2">
                            <x-input-label :value="__('players.hints.current_rating')" />
                            <div class="mt-1 p-2.5 bg-gray-100 border border-gray-300 rounded-md text-gray-700 font-bold sm:w-1/2">
                                {{ $player->current_rating }}
                            </div>
                            <p class="text-xs text-gray-500 mt-1">{{ __('players.hints.rating_locked') }}</p>
                        </div>

                        <!-- Notes -->
                        <div class="sm:col-span-2">
                            <x-input-label for="notes" :value="__('players.fields.notes')" />
                            <textarea id="notes" name="notes" rows="3" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">{{ old('notes', $player->notes) }}</textarea>
                            <x-input-error :messages="$errors->get('notes')" class="mt-2" />
                        </div>
                    </div>

                    <div class="flex items-center justify-between pt-4 border-t border-gray-200">
                        <span class="text-xs text-gray-400">{{ __('players.edit.version', ['version' => $player->version]) }}</span>

                        <div class="flex items-center space-x-3">
                            <a href="{{ route('players.show', $player) }}" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300 text-sm font-medium transition">
                                {{ __('common.cancel') }}
                            </a>
                            <x-primary-button>
                                {{ __('players.edit.submit') }}
                            </x-primary-button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
