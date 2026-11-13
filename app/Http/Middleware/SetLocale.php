<?php

namespace App\Http\Middleware;

use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * French or English: the user's choice, then ?lang=, then the browser, then the agency default.
 */
final class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $supported = config('platform.locales');

        $candidates = [
            $request->user()?->locale ?? null,
            $request->query('lang'),
            $request->hasSession() ? $request->session()->get('locale') : null,
            $request->headers->get('Accept-Language') ? $request->getPreferredLanguage($supported) : null,
            app(TenantContext::class)->tenant()?->default_locale,
        ];

        foreach ($candidates as $locale) {
            if (is_string($locale) && in_array($locale, $supported, true)) {
                app()->setLocale($locale);
                if ($request->hasSession() && $request->query('lang') === $locale) {
                    $request->session()->put('locale', $locale);
                }
                break;
            }
        }

        return $next($request);
    }
}
