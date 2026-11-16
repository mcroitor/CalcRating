<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    {{ __('tournaments.edit.title') }}
                </h2>
                <div class="text-sm text-gray-500 mt-1">{{ $tournament->name }}</div>
            </div>
            <a href="{{ route('tournaments.show', $tournament) }}" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md text-sm font-medium hover:bg-gray-300 transition">
                {{ __('tournaments.edit.back') }}
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow sm:rounded-lg p-6">
                @include('tournaments._form', [
                    'action' => route('tournaments.update', $tournament),
                    'submitLabel' => __('tournaments.edit.submit'),
                ])
            </div>
        </div>
    </div>
</x-app-layout>
