<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('tournaments.create.title') }}
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow sm:rounded-lg p-6">
                @include('tournaments._form', [
                    'tournament' => null,
                    'action' => route('tournaments.store'),
                    'submitLabel' => __('tournaments.create.submit'),
                ])
            </div>
        </div>
    </div>
</x-app-layout>
