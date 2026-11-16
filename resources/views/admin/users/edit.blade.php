<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <h2 class="font-semibold text-xl text-gray-800">{{ __('admin.edit.title') }}</h2>
            @if($user->is_blocked)
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-red-100 text-red-800">{{ __('admin.edit.blocked') }}</span>
            @else
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-800">{{ __('admin.edit.active') }}</span>
            @endif
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if($user->is_blocked)
                <div class="p-4 bg-red-50 border-l-4 border-red-400 text-red-700 rounded-md text-sm">
                    {{ __('admin.edit.blocked_notice', ['date' => \App\Support\DateFormatter::format($user->blocked_at, true) ?? '']) }}
                    @if($user->blocked_reason)
                        {{ __('admin.edit.blocked_reason', ['reason' => $user->blocked_reason]) }}
                    @endif
                    {{ __('admin.edit.unblock_hint') }}
                </div>
            @endif

            <div class="bg-white shadow sm:rounded-lg p-6">
                <form method="POST" action="{{ route('admin.users.update', $user) }}" class="space-y-4">
                    @csrf
                    @method('PUT')

                    <div>
                        <label for="surname" class="block text-sm font-medium text-gray-700">{{ __('admin.edit.surname') }}</label>
                        <input id="surname" name="surname" value="{{ old('surname', $user->surname) }}" required
                               class="mt-1 w-full rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                        @error('surname') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-700">{{ __('admin.edit.name') }}</label>
                        <input id="name" name="name" value="{{ old('name', $user->name) }}" required
                               class="mt-1 w-full rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                        @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
                        <input id="email" name="email" type="email" value="{{ old('email', $user->email) }}" required
                               class="mt-1 w-full rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                        @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="role" class="block text-sm font-medium text-gray-700">{{ __('admin.edit.role') }}</label>
                        <select id="role" name="role" required
                                class="mt-1 w-full rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                            @foreach(\App\Enums\UserRole::cases() as $role)
                                <option value="{{ $role->value }}" @selected(old('role', $user->role->value) === $role->value)>{{ $role->label() }}</option>
                            @endforeach
                        </select>
                        @error('role') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        @if($user->id === auth()->id())
                            <p class="mt-1 text-xs text-gray-500">{{ __('admin.edit.self_role_hint') }}</p>
                        @endif
                    </div>

                    <input type="hidden" name="version" value="{{ $user->version }}">

                    <div class="flex items-center gap-2">
                        <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md text-sm font-medium hover:bg-indigo-700 transition">
                            {{ __('common.save') }}
                        </button>
                        <a href="{{ route('admin.users.index') }}" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md text-sm font-medium hover:bg-gray-300 transition">
                            {{ __('common.cancel') }}
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
