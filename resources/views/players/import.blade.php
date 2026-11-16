<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('players.import.title') }}
            </h2>
            <a href="{{ route('players.index') }}" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md text-sm font-medium hover:bg-gray-300 transition">
                {{ __('players.import.back') }}
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <!-- Results Block -->
            @if(session('import_result'))
                @php $res = session('import_result'); @endphp
                <div class="bg-white shadow sm:rounded-lg p-6 space-y-4">
                    <h3 class="text-lg font-bold text-gray-900 border-b border-gray-200 pb-2">{{ __('players.import.results') }}</h3>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-center">
                        <div class="p-4 bg-gray-50 rounded-lg">
                            <div class="text-2xl font-bold text-gray-800">{{ $res['total_rows'] }}</div>
                            <div class="text-xs text-gray-500 uppercase mt-1">{{ __('players.import.total_rows') }}</div>
                        </div>
                        <div class="p-4 bg-green-50 rounded-lg">
                            <div class="text-2xl font-bold text-green-700">{{ $res['imported'] }}</div>
                            <div class="text-xs text-green-600 uppercase mt-1">{{ __('players.import.imported') }}</div>
                        </div>
                        <div class="p-4 bg-red-50 rounded-lg">
                            <div class="text-2xl font-bold text-red-700">{{ $res['errors_count'] }}</div>
                            <div class="text-xs text-red-600 uppercase mt-1">{{ __('players.import.errors') }}</div>
                        </div>
                    </div>

                    @if(!empty($res['warnings']))
                        <div class="p-4 bg-yellow-50 border-l-4 border-yellow-400 text-yellow-800 rounded-md space-y-1">
                            <h4 class="font-bold text-sm">{{ __('players.import.warnings') }}</h4>
                            <ul class="list-disc list-inside text-xs space-y-1">
                                @foreach($res['warnings'] as $warning)
                                    <li>{{ __('players.import.row', ['row' => $warning['row']]) }}: {{ $warning['message'] }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if(!empty($res['errors']))
                        <div class="p-4 bg-red-50 border-l-4 border-red-400 text-red-800 rounded-md space-y-1">
                            <h4 class="font-bold text-sm">{{ __('players.import.validation_errors') }}</h4>
                            <ul class="list-disc list-inside text-xs space-y-1">
                                @foreach($res['errors'] as $error)
                                    <li>{{ __('players.import.row', ['row' => $error['row']]) }}: {{ $error['message'] }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>
            @endif

            <!-- Upload Form -->
            <div class="bg-white shadow sm:rounded-lg p-6 space-y-6">
                <div>
                    <h3 class="text-lg font-bold text-gray-900 mb-2">{{ __('players.import.upload') }}</h3>
                    <p class="text-sm text-gray-600">
                        {!! __('players.import.formats') !!}
                        <code class="bg-gray-100 px-1 py-0.5 rounded text-indigo-700 text-xs">first_name</code>,
                        <code class="bg-gray-100 px-1 py-0.5 rounded text-indigo-700 text-xs">last_name</code>,
                        <code class="bg-gray-100 px-1 py-0.5 rounded text-indigo-700 text-xs">birth_date</code>,
                        <code class="bg-gray-100 px-1 py-0.5 rounded text-indigo-700 text-xs">current_rating</code> {{ __('players.import.optional_rating') }},
                        <code class="bg-gray-100 px-1 py-0.5 rounded text-indigo-700 text-xs">title</code> {{ __('players.import.optional_title') }},
                        <code class="bg-gray-100 px-1 py-0.5 rounded text-indigo-700 text-xs">notes</code>.
                    </p>
                </div>

                <form method="POST" action="{{ route('players.import.store') }}" enctype="multipart/form-data" class="space-y-6">
                    @csrf

                    <div>
                        <x-input-label for="file" :value="__('players.import.choose_file')" />
                        <input id="file" name="file" type="file" accept=".csv,.xlsx,.ods" required
                               class="mt-1 block w-full text-sm text-gray-500
                                      file:mr-4 file:py-2 file:px-4
                                      file:rounded-md file:border-0
                                      file:text-sm file:font-semibold
                                      file:bg-indigo-50 file:text-indigo-700
                                      hover:file:bg-indigo-100" />
                        <x-input-error :messages="$errors->get('file')" class="mt-2" />
                    </div>

                    @if(($clubs ?? collect())->isNotEmpty())
                        <div>
                            <x-input-label for="club_id" :value="__('players.import.club_label')" />
                            <select id="club_id" name="club_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">{{ __('players.import.no_club') }}</option>
                                @foreach($clubs as $club)
                                    <option value="{{ $club->id }}" @selected((string) old('club_id') === (string) $club->id)>
                                        {{ $club->name }}
                                    </option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('club_id')" class="mt-2" />
                        </div>
                    @endif

                    <div class="pt-4 border-t border-gray-200 flex items-center justify-end space-x-3">
                        <a href="{{ route('players.index') }}" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300 text-sm font-medium transition">
                            {{ __('common.cancel') }}
                        </a>
                        <x-primary-button>
                            {{ __('players.import.submit') }}
                        </x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
