<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Finds the agency from the host name ({slug}.base-domain or a verified custom domain).
 * Unknown or suspended agencies get a 404 so we don't reveal which agencies exist.
 *
 * Demo hosting with one fixed address and no sub-domains (GitHub Codespaces) can name one agency in
 * PLATFORM_DEMO_AGENCY to serve on any host. That setting is ignored in production.
 */
final class ResolveTenant
{
    public function __construct(private readonly TenantContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $host = strtolower($request->getHost());

        $domain = TenantDomain::query()
            ->with('tenant')
            ->where('domain', $host)
            ->where(fn ($q) => $q->whereNotNull('verified_at')->orWhere('domain', 'like', '%.'.config('platform.base_domain')))
            ->first();

        $tenant = $domain?->tenant ?? $this->demoAgency();

        abort_if($tenant === null || ! $tenant->isActive(), 404);

        $this->context->set($tenant);

        // The agency is left again when the request ends (AppServiceProvider), not here: Livewire runs its component
        // after this middleware has returned, and the component must still be inside the agency.
        return $next($request);
    }

    private function demoAgency(): ?Tenant
    {
        $slug = config('platform.demo_agency');

        if (blank($slug) || app()->isProduction()) {
            return null;
        }

        return Tenant::query()->where('slug', $slug)->first();
    }
}
