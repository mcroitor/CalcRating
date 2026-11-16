<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('clubs.edit.title', ['name' => $club->name]) }}
            </h2>
            <a href="{{ route('clubs.show', $club) }}" class="text-sm text-gray-600 hover:text-gray-900">
                {{ __('clubs.edit.back') }}
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow sm:rounded-lg p-6">
                <form method="POST" action="{{ route('clubs.update', $club) }}" enctype="multipart/form-data" class="space-y-4">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="version" value="{{ $club->version }}">

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="md:col-span-2">
                            <label for="name" class="block text-sm font-medium text-gray-700">{{ __('clubs.fields.name') }}</label>
                            <input id="name" name="name" type="text" value="{{ old('name', $club->name) }}" required
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <x-input-error :messages="$errors->get('name')" class="mt-2" />
                        </div>

                        <div>
                            <label for="code" class="block text-sm font-medium text-gray-700">{{ __('clubs.fields.code') }}</label>
                            <input id="code" name="code" type="text" maxlength="32" value="{{ old('code', $club->code) }}"
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <x-input-error :messages="$errors->get('code')" class="mt-2" />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="city" class="block text-sm font-medium text-gray-700">{{ __('clubs.fields.city') }}</label>
                            <input id="city" name="city" type="text" value="{{ old('city', $club->city) }}"
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <x-input-error :messages="$errors->get('city')" class="mt-2" />
                        </div>

                        <div>
                            <label for="website" class="block text-sm font-medium text-gray-700">{{ __('clubs.fields.website') }}</label>
                            <input id="website" name="website" type="url" value="{{ old('website', $club->website) }}"
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <x-input-error :messages="$errors->get('website')" class="mt-2" />
                        </div>
                    </div>

                    <div>
                        <label for="address" class="block text-sm font-medium text-gray-700">{{ __('clubs.fields.address') }}</label>
                        <input id="address" name="address" type="text" value="{{ old('address', $club->address) }}"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <x-input-error :messages="$errors->get('address')" class="mt-2" />
                    </div>

                    <div>
                        <label for="description" class="block text-sm font-medium text-gray-700">{{ __('clubs.fields.description') }}</label>
                        <textarea id="description" name="description" rows="3"
                                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('description', $club->description) }}</textarea>
                        <x-input-error :messages="$errors->get('description')" class="mt-2" />
                    </div>

                    <div class="space-y-2">
                        <label for="logo" class="block text-sm font-medium text-gray-700">{{ __('clubs.fields.logo') }}</label>
                        @if($club->logoUrl())
                            <div class="flex items-center gap-4">
                                <img src="{{ $club->logoUrl() }}" alt="{{ $club->name }}" class="w-14 h-14 rounded-lg object-cover border border-gray-200">
                                <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                                    <input type="checkbox" name="remove_logo" value="1" class="rounded border-gray-300 text-indigo-600">
                                    <span>{{ __('clubs.fields.remove_logo') }}</span>
                                </label>
                            </div>
                        @endif
                        <input id="logo" name="logo" type="file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                               class="mt-1 block w-full text-sm text-gray-700">
                        <x-input-error :messages="$errors->get('logo')" class="mt-2" />
                    </div>

                    <div class="pt-4 flex items-center gap-3">
                        <button type="submit" class="px-5 py-2.5 bg-indigo-600 text-white rounded-md text-sm font-medium hover:bg-indigo-700 transition">
                            {{ __('clubs.edit.submit') }}
                        </button>
                        <a href="{{ route('clubs.show', $club) }}" class="px-5 py-2.5 bg-gray-200 text-gray-700 rounded-md text-sm font-medium hover:bg-gray-300 transition">
                            {{ __('common.cancel') }}
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
