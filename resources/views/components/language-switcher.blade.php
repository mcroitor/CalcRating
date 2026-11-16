@php
    $locales = config('locales.supported', ['en']);
    $names = config('locales.names', []);
    $current = app()->getLocale();
@endphp

<nav aria-label="{{ __('nav.language') }}" class="inline-flex items-center rounded-md border border-gray-200 bg-white p-0.5 text-xs font-semibold">
    @foreach($locales as $locale)
        @if($locale === $current)
            <span lang="{{ $locale }}" hreflang="{{ $locale }}" aria-current="true"
                  title="{{ $names[$locale] ?? strtoupper($locale) }}"
                  class="px-2 py-1 rounded bg-indigo-600 text-white">
                {{ strtoupper($locale) }}
            </span>
        @else
            <a href="{{ route('locale.update', $locale) }}" lang="{{ $locale }}" hreflang="{{ $locale }}"
               title="{{ $names[$locale] ?? strtoupper($locale) }}"
               class="px-2 py-1 rounded text-gray-600 hover:bg-gray-100 hover:text-gray-900">
                {{ strtoupper($locale) }}
            </a>
        @endif
    @endforeach
</nav>
