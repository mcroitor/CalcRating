<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('welcome.title') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="antialiased bg-gray-50 text-gray-900 min-h-screen flex flex-col">
    <header class="bg-white border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <span class="text-2xl font-bold text-indigo-600">♟ CalcRating</span>
            </div>
            <nav class="flex items-center space-x-4">
                <a href="{{ route('clubs.index') }}" class="text-sm font-medium text-gray-700 hover:text-indigo-600">
                    {{ __('nav.clubs') }}
                </a>
                <a href="{{ route('players.index') }}" class="text-sm font-medium text-gray-700 hover:text-indigo-600">
                    {{ __('nav.players') }}
                </a>
                @auth
                    <a href="{{ route('dashboard') }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-800">
                        {{ __('nav.dashboard') }} ({{ Auth::user()->name }})
                    </a>
                @else
                    <a href="{{ route('login') }}" class="text-sm font-medium text-gray-700 hover:text-gray-900">
                        {{ __('nav.login') }}
                    </a>
                    @if (Route::has('register'))
                        <a href="{{ route('register') }}" class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-md hover:bg-indigo-700 transition">
                            {{ __('nav.register') }}
                        </a>
                    @endif
                @endauth
                <x-language-switcher />
            </nav>
        </div>
    </header>

    <main class="flex-grow max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div class="text-center max-w-3xl mx-auto mb-12">
            <h1 class="text-4xl font-extrabold text-gray-900 tracking-tight sm:text-5xl">
                {{ __('welcome.hero_title') }}
            </h1>
            <p class="mt-4 text-lg text-gray-600">
                {{ __('welcome.hero_text') }}
            </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 max-w-5xl mx-auto">
            <a href="{{ route('players.index') }}" class="block bg-white p-6 rounded-lg shadow-sm border border-gray-200 hover:border-indigo-400 hover:shadow-md transition">
                <div class="w-10 h-10 bg-indigo-100 text-indigo-600 rounded-lg flex items-center justify-center font-bold mb-4">
                    👥
                </div>
                <h2 class="text-xl font-semibold mb-2 text-gray-900">{{ __('welcome.players_title') }}</h2>
                <p class="text-gray-600 text-sm">
                    {{ __('welcome.players_text') }}
                </p>
            </a>

            <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
                <div class="w-10 h-10 bg-indigo-100 text-indigo-600 rounded-lg flex items-center justify-center font-bold mb-4">
                    🏆
                </div>
                <h2 class="text-xl font-semibold mb-2">{{ __('welcome.tournaments_title') }}</h2>
                <p class="text-gray-600 text-sm">
                    {{ __('welcome.tournaments_text') }}
                </p>
            </div>

            <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
                <div class="w-10 h-10 bg-indigo-100 text-indigo-600 rounded-lg flex items-center justify-center font-bold mb-4">
                    📈
                </div>
                <h2 class="text-xl font-semibold mb-2">{{ __('welcome.elo_title') }}</h2>
                <p class="text-gray-600 text-sm">
                    {{ __('welcome.elo_text') }}
                </p>
            </div>
        </div>
    </main>

    <footer class="bg-white border-t border-gray-200 py-6 text-center text-sm text-gray-500">
        {{ __('welcome.footer', ['year' => date('Y')]) }}
    </footer>
</body>
</html>
