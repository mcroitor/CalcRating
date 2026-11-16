<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('dashboard.title') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <div class="text-gray-900">
                    <h3 class="text-lg font-bold mb-2">{{ __('dashboard.welcome', ['name' => Auth::user()->name, 'surname' => Auth::user()->surname]) }}</h3>
                    <p class="text-sm text-gray-600 mb-4">
                        {{ __('dashboard.current_role') }} <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800">{{ Auth::user()->role?->label() ?? Auth::user()->role }}</span>
                    </p>

                    @if(Auth::user()->isGuest())
                        <div class="bg-blue-50 border-l-4 border-blue-400 p-4 mb-4">
                            <p class="text-sm text-blue-700">
                                {{ __('dashboard.guest_hint') }}
                            </p>
                        </div>
                    @elseif(Auth::user()->isArbiter())
                        <div class="bg-green-50 border-l-4 border-green-400 p-4 mb-4">
                            <p class="text-sm text-green-700">
                                {{ __('dashboard.arbiter_hint') }}
                            </p>
                        </div>
                    @elseif(Auth::user()->isAdmin())
                        <div class="bg-purple-50 border-l-4 border-purple-400 p-4 mb-4">
                            <p class="text-sm text-purple-700">
                                {{ __('dashboard.admin_hint') }}
                            </p>
                        </div>

                        <div class="flex flex-wrap gap-3">
                            <a href="{{ route('admin.users.index') }}"
                               class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded-md text-sm font-medium hover:bg-indigo-700 transition">
                                {{ __('nav.users') }}
                            </a>
                            <a href="{{ route('clubs.index') }}"
                               class="inline-flex items-center px-4 py-2 bg-gray-200 text-gray-800 rounded-md text-sm font-medium hover:bg-gray-300 transition">
                                {{ __('nav.clubs') }}
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
