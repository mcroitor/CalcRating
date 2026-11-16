<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <h2 class="font-semibold text-xl text-gray-800">{{ __('admin.index.title') }}</h2>
            <a href="{{ route('admin.users.create') }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded-md text-sm font-medium hover:bg-indigo-700 transition">
                {{ __('admin.index.create') }}
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if(session('success'))
                <div class="p-4 bg-green-50 border-l-4 border-green-400 text-green-700 rounded-md">{{ session('success') }}</div>
            @endif

            @if(session('error'))
                <div class="p-4 bg-red-50 border-l-4 border-red-400 text-red-700 rounded-md">{{ session('error') }}</div>
            @endif

            @if(session('warning'))
                <div class="p-4 bg-yellow-50 border-l-4 border-yellow-400 text-yellow-800 rounded-md">{{ session('warning') }}</div>
            @endif

            <form method="GET" action="{{ route('admin.users.index') }}" class="bg-white p-4 shadow sm:rounded-lg grid grid-cols-1 sm:grid-cols-4 gap-3 items-end">
                <div class="sm:col-span-2">
                    <label for="search" class="block text-xs font-semibold uppercase tracking-wider text-gray-500 mb-1">{{ __('admin.index.search_label') }}</label>
                    <input id="search" name="search" type="search" value="{{ request('search') }}" placeholder="{{ __('admin.index.search_placeholder') }}"
                           class="w-full rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>

                <div>
                    <label for="role" class="block text-xs font-semibold uppercase tracking-wider text-gray-500 mb-1">{{ __('admin.index.role_label') }}</label>
                    <select id="role" name="role" class="w-full rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">{{ __('admin.index.all_roles') }}</option>
                        @foreach(\App\Enums\UserRole::cases() as $roleOption)
                            <option value="{{ $roleOption->value }}" @selected(request('role') === $roleOption->value)>{{ $roleOption->label() }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="status" class="block text-xs font-semibold uppercase tracking-wider text-gray-500 mb-1">{{ __('admin.index.status_label') }}</label>
                    <select id="status" name="status" class="w-full rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">{{ __('admin.index.all_statuses') }}</option>
                        <option value="active" @selected(request('status') === 'active')>{{ __('admin.index.status_active') }}</option>
                        <option value="blocked" @selected(request('status') === 'blocked')>{{ __('admin.index.status_blocked') }}</option>
                    </select>
                </div>

                <div class="sm:col-span-4 flex flex-wrap gap-2">
                    <button type="submit" class="px-4 py-2 bg-gray-800 text-white rounded-md text-sm font-medium hover:bg-gray-900 transition">
                        {{ __('common.apply') }}
                    </button>
                    <a href="{{ route('admin.users.index') }}" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md text-sm font-medium hover:bg-gray-300 transition">
                        {{ __('common.reset') }}
                    </a>
                </div>
            </form>

            {{-- Desktop table --}}
            <div class="hidden md:block bg-white shadow sm:rounded-lg overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left font-semibold text-gray-600">{{ __('admin.index.col_name') }}</th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-600">{{ __('admin.index.col_email') }}</th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-600">{{ __('admin.index.col_role') }}</th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-600">{{ __('admin.index.col_status') }}</th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-600">{{ __('admin.index.col_blocked_at') }}</th>
                            <th class="px-4 py-3 text-right font-semibold text-gray-600">{{ __('admin.index.col_actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($users as $user)
                            <tr class="align-top">
                                <td class="px-4 py-3 font-medium text-gray-900">{{ $user->surname }} {{ $user->name }}</td>
                                <td class="px-4 py-3 text-gray-700">{{ $user->email }}</td>
                                <td class="px-4 py-3">
                                    @php
                                        $roleClasses = match ($user->role) {
                                            App\Enums\UserRole::Admin => 'bg-indigo-100 text-indigo-800',
                                            App\Enums\UserRole::Arbiter => 'bg-emerald-100 text-emerald-800',
                                            App\Enums\UserRole::Guest => 'bg-gray-100 text-gray-700',
                                        };
                                    @endphp
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold {{ $roleClasses }}">
                                        {{ $user->role->label() }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    @if($user->is_blocked)
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-red-100 text-red-800">{{ __('admin.index.blocked') }}</span>
                                    @else
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-800">{{ __('admin.index.active') }}</span>
                                    @endif
                                    @if($user->is_blocked && $user->blocked_reason)
                                        <div class="text-xs text-gray-500 mt-1">{{ $user->blocked_reason }}</div>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-gray-700">{{ \App\Support\DateFormatter::format($user->blocked_at, true) ?? '—' }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-wrap items-center justify-end gap-2">
                                        <a href="{{ route('admin.users.edit', $user) }}" class="px-3 py-1.5 bg-gray-200 text-gray-700 rounded-md text-xs font-medium hover:bg-gray-300 transition">
                                            {{ __('admin.index.edit') }}
                                        </a>

                                        <form method="POST" action="{{ route('admin.users.reset-password', $user) }}"
                                              onsubmit="return confirm('{{ __('admin.index.reset_confirm') }}');">
                                            @csrf
                                            <button type="submit" class="px-3 py-1.5 bg-sky-600 text-white rounded-md text-xs font-medium hover:bg-sky-700 transition">
                                                {{ __('admin.index.reset_password') }}
                                            </button>
                                        </form>

                                        @if($user->is_blocked)
                                            <form method="POST" action="{{ route('admin.users.unblock', $user) }}"
                                                  onsubmit="return confirm('{{ __('admin.index.unblock_confirm') }}');">
                                                @csrf
                                                <input type="hidden" name="version" value="{{ $user->version }}">
                                                <button type="submit" class="px-3 py-1.5 bg-green-600 text-white rounded-md text-xs font-medium hover:bg-green-700 transition">
                                                    {{ __('admin.index.unblock') }}
                                                </button>
                                            </form>
                                        @else
                                            @can('block', $user)
                                                <form method="POST" action="{{ route('admin.users.block', $user) }}" class="flex items-center gap-1"
                                                      onsubmit="return confirm('{{ __('admin.index.block_confirm') }}');">
                                                    @csrf
                                                    <input type="hidden" name="version" value="{{ $user->version }}">
                                                    <input type="text" name="reason" placeholder="{{ __('admin.index.reason_placeholder') }}" maxlength="255"
                                                           class="w-28 rounded-md border-gray-300 text-xs focus:border-red-500 focus:ring-red-500">
                                                    <button type="submit" class="px-3 py-1.5 bg-red-600 text-white rounded-md text-xs font-medium hover:bg-red-700 transition">
                                                        {{ __('admin.index.block') }}
                                                    </button>
                                                </form>
                                            @endcan
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-6 text-center text-gray-500">{{ __('admin.index.not_found') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Mobile cards --}}
            <div class="md:hidden space-y-3">
                @forelse($users as $user)
                    <div class="bg-white shadow sm:rounded-lg p-4 space-y-2">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <div class="font-semibold text-gray-900">{{ $user->surname }} {{ $user->name }}</div>
                                <div class="text-sm text-gray-600 break-all">{{ $user->email }}</div>
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center gap-2">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-700">{{ $user->role->label() }}</span>
                            @if($user->is_blocked)
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-red-100 text-red-800">{{ __('admin.index.blocked') }}</span>
                            @else
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-800">{{ __('admin.index.active') }}</span>
                            @endif
                        </div>

                        @if($user->is_blocked)
                            <div class="text-xs text-gray-500">
                                {{ __('admin.index.blocked_at', ['date' => \App\Support\DateFormatter::format($user->blocked_at, true) ?? '—']) }}
                                @if($user->blocked_reason)
                                    <br>{{ $user->blocked_reason }}
                                @endif
                            </div>
                        @endif

                        <div class="flex flex-wrap gap-2 pt-1">
                            <a href="{{ route('admin.users.edit', $user) }}" class="px-3 py-1.5 bg-gray-200 text-gray-700 rounded-md text-xs font-medium">{{ __('admin.index.edit') }}</a>

                            <form method="POST" action="{{ route('admin.users.reset-password', $user) }}"
                                  onsubmit="return confirm('{{ __('admin.index.reset_confirm') }}');">
                                @csrf
                                <button type="submit" class="px-3 py-1.5 bg-sky-600 text-white rounded-md text-xs font-medium">{{ __('admin.index.reset_password') }}</button>
                            </form>

                            @if($user->is_blocked)
                                <form method="POST" action="{{ route('admin.users.unblock', $user) }}"
                                      onsubmit="return confirm('{{ __('admin.index.unblock_confirm') }}');">
                                    @csrf
                                    <input type="hidden" name="version" value="{{ $user->version }}">
                                    <button type="submit" class="px-3 py-1.5 bg-green-600 text-white rounded-md text-xs font-medium">{{ __('admin.index.unblock') }}</button>
                                </form>
                            @else
                                @can('block', $user)
                                    <form method="POST" action="{{ route('admin.users.block', $user) }}" class="flex items-center gap-1"
                                          onsubmit="return confirm('{{ __('admin.index.block_confirm') }}');">
                                        @csrf
                                        <input type="hidden" name="version" value="{{ $user->version }}">
                                        <input type="text" name="reason" placeholder="{{ __('admin.index.reason_placeholder') }}" maxlength="255"
                                               class="w-28 rounded-md border-gray-300 text-xs">
                                        <button type="submit" class="px-3 py-1.5 bg-red-600 text-white rounded-md text-xs font-medium">{{ __('admin.index.block') }}</button>
                                    </form>
                                @endcan
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="bg-white shadow sm:rounded-lg p-4 text-center text-gray-500">{{ __('admin.index.not_found') }}</div>
                @endforelse
            </div>

            <div>{{ $users->links() }}</div>
        </div>
    </div>
</x-app-layout>
