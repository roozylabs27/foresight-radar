<?php

namespace App\Http\Middleware;

use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetAppLocale
{
    /**
     * Supported application locales.
     *
     * @var array<int, string>
     */
    public const SUPPORTED_LOCALES = ['id', 'en'];

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->determineLocale($request);

        app()->setLocale($locale);
        Carbon::setLocale($locale);

        $response = $next($request);

        $response->headers->set('Content-Language', $locale);

        return $response;
    }

    /**
     * Determine the active locale with session and cookie precedence (no URL prefix, no user DB relation).
     */
    protected function determineLocale(Request $request): string
    {
        $candidate = $request->session()->get('locale')
            ?: $request->cookie('app_locale')
            ?: $request->header('X-Locale');

        if (is_string($candidate) && in_array(strtolower($candidate), self::SUPPORTED_LOCALES, true)) {
            return strtolower($candidate);
        }

        return (string) config('app.locale', 'id');
    }
}
