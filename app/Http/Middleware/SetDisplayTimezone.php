<?php

namespace App\Http\Middleware;

use App\Tenancy\TenantContext;
use Closure;
use Filament\Support\Facades\FilamentTimezone;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Times are stored in UTC and shown (and typed) in the agency's own time zone.
 */
final class SetDisplayTimezone
{
    public function handle(Request $request, Closure $next): Response
    {
        $timezone = app(TenantContext::class)->tenant()?->timezone;

        if (is_string($timezone) && in_array($timezone, timezone_identifiers_list(), true)) {
            FilamentTimezone::set($timezone);
        }

        return $next($request);
    }
}
