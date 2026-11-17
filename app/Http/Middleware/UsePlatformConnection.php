<?php

namespace App\Http\Middleware;

use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Super-admin panel only, and only on admin.{base_domain}. Switches to the database role that can see every agency.
 */
final class UsePlatformConnection
{
    public function handle(Request $request, Closure $next): Response
    {
        $adminHost = config('platform.admin_subdomain').'.'.config('platform.base_domain');
        abort_unless(strtolower($request->getHost()) === $adminHost, 404);

        DB::setDefaultConnection('pgsql_platform');
        app(TenantContext::class)->enterPlatformMode();

        // Reset when the request ends (AppServiceProvider), so Livewire actions also run on the platform connection.
        return $next($request);
    }
}
