<?php

namespace App\Http\Middleware;

use App\Models\TenantDomain;
use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Finds the agency from the host name ({slug}.base-domain or a verified custom domain).
 * Unknown or suspended agencies get a 404 so we don't reveal which agencies exist.
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

        abort_if($domain === null || ! $domain->tenant->isActive(), 404);

        $this->context->set($domain->tenant);

        try {
            return $next($request);
        } finally {
            // Long-running workers (queues, Octane) must never carry one agency into the next request.
            $this->context->clear();
        }
    }
}
