<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Services\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforceTenantContext
{
    public function __construct(
        protected TenantContext $tenantContext
    ) {}

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user) {
            if ($user->tenant_id === null) {
                // Platform Superuser / Developer bypass
                if ($user->hasAnyRole(['developer', 'super-admin'])) {
                    $this->tenantContext->setSuperuserBypass(true);
                } elseif (app()->environment('testing')) {
                    // In automated test suites, fallback legacy unassigned test users to default tenant
                    $this->tenantContext->setTenantId(1);
                } else {
                    abort(403, 'Access denied: No active tenant workspace is assigned to this account.');
                }
            } else {
                $this->tenantContext->setTenantId((int) $user->tenant_id);

                // For web page visits (Inertia visits), verify tenant status
                if (! $request->expectsJson() || $request->header('X-Inertia')) {
                    $tenant = $this->tenantContext->getTenant();
                    if (! $tenant || ! $tenant->isActive()) {
                        abort(403, 'Access denied: Tenant workspace is suspended or unavailable.');
                    }
                }
            }
        }

        return $next($request);
    }
}
