<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('players.create.title') }}
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow sm:rounded-lg p-6">
                <form method="POST" action="{{ route('players.store') }}" class="space-y-6">
                    @csrf

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                        <!-- First Name -->
                        <div>
                            <x-input-label for="first_name" :value="__('players.fields.first_name') . ' *'" />
                            <x-text-input id="first_name" name="first_name" type="text" class="mt-1 block w-full" :value="old('first_name')" required autofocus />
                            <x-input-error :messages="$errors->get('first_name')" class="mt-2" />
                        </div>

                        <!-- Last Name -->
                        <div>
                            <x-input-label for="last_name" :value="__('players.fields.last_name') . ' *'" />
                            <x-text-input id="last_name" name="last_name" type="text" class="mt-1 block w-full" :value="old('last_name')" required />
                            <x-input-error :messages="$errors->get('last_name')" class="mt-2" />
                        </div>

                        <!-- Birth Date -->
                        <div>
                            <x-input-label for="birth_date" :value="__('players.fields.birth_date') . ' *'" />
                            <x-text-input id="birth_date" name="birth_date" type="date" class="mt-1 block w-full" :value="old('birth_date')" required />
                            <x-input-error :messages="$errors->get('birth_date')" class="mt-2" />
                        </div>

                        <!-- Title -->
                        <div>
                            <x-input-label for="title" :value="__('players.fields.title')" />
                            <select id="title" name="title" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                @foreach(App\Enums\ChessTitle::cases() as $titleCase)
                                    <option value="{{ $titleCase->value }}" @selected(old('title', 'None') === $titleCase->value)>
                                        {{ $titleCase->label() }}
                                    </option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('title')" class="mt-2" />
                        </div>

                        <!-- Current Rating -->
                        <div class="sm:col-span-2">
                            <x-input-label for="current_rating" :value="__('players.fields.current_rating') . ' *'" />
                            <x-text-input id="current_rating" name="current_rating" type="number" min="0" class="mt-1 block w-full sm:w-1/2" :value="old('current_rating', 1000)" required />
                            <p class="text-xs text-gray-500 mt-1">{{ __('players.hints.create_rating') }}</p>
                            <x-input-error :messages="$errors->get('current_rating')" class="mt-2" />
                        </div>

                        <!-- Notes -->
                        <div class="sm:col-span-2">
                            <x-input-label for="notes" :value="__('players.fields.notes')" />
                            <textarea id="notes" name="notes" rows="3" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">{{ old('notes') }}</textarea>
                            <x-input-error :messages="$errors->get('notes')" class="mt-2" />
                        </div>
                    </div>

                    <div class="flex items-center justify-end space-x-3 pt-4 border-t border-gray-200">
                        <a href="{{ route('players.index') }}" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300 text-sm font-medium transition">
                            {{ __('common.cancel') }}
                        </a>
                        <x-primary-button>
                            {{ __('players.create.submit') }}
                        </x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
