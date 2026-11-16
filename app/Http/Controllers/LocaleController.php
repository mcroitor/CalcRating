<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LocaleController extends Controller
{
    public function update(Request $request, string $locale): RedirectResponse
    {
        $supported = (array) config('locales.supported', ['en']);

        abort_unless(in_array($locale, $supported, true), 404);

        $request->session()->put('locale', $locale);

        $cookie = cookie(
            config('locales.cookie', 'locale'),
            $locale,
            (int) config('locales.cookie_lifetime', 525600),
            '/',
            null,
            $request->isSecure(),
            true,
            false,
            'lax'
        );

        $fallback = $request->user() ? route('dashboard') : url('/');
        $previous = url()->previous($fallback);
        $previousPath = (string) parse_url($previous, PHP_URL_PATH);

        if (str_starts_with($previousPath, '/locale/')) {
            return redirect()->to($fallback)->withCookie($cookie);
        }

        return redirect()->back(302, [], $fallback)->withCookie($cookie);
    }
}
