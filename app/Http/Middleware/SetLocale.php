<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->resolve($request);

        App::setLocale($locale);
        Carbon::setLocale($locale);

        $response = $next($request);

        $this->addVaryHeader($response);

        return $response;
    }

    private function resolve(Request $request): string
    {
        $supported = (array) config('locales.supported', ['en']);

        $candidates = [
            $request->hasSession() ? $request->session()->get('locale') : null,
            $request->cookie(config('locales.cookie', 'locale')),
            config('locales.default', 'en'),
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && in_array($candidate, $supported, true)) {
                return $candidate;
            }
        }

        return (string) config('locales.fallback', 'en');
    }

    private function addVaryHeader(Response $response): void
    {
        if (! str_contains((string) $response->headers->get('Content-Type'), 'text/html')) {
            return;
        }

        $vary = (string) $response->headers->get('Vary');

        if ($vary === '') {
            $response->headers->set('Vary', 'Cookie');

            return;
        }

        if (! str_contains(strtolower($vary), 'cookie')) {
            $response->headers->set('Vary', $vary.', Cookie');
        }
    }
}
