<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Services\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforceTenantContext
{
    /** Accounts without a tenant that may still sign in: platform operators. */
    public const PLATFORM_ROLES = ['developer', 'super-admin'];

    /** Session key holding the tenant id a platform operator picked. */
    public const WORKSPACE_SESSION_KEY = 'operator_tenant_id';

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
                if ($user->hasAnyRole(self::PLATFORM_ROLES)) {
                    // Platform operators work inside one workspace they pick; without one they see
                    // and write nothing tenant-owned, instead of every tenant merged (TENANT-007).
                    $workspace = $this->operatorWorkspace($request);
                    $this->tenantContext->setTenant($workspace);
                    $this->tenantContext->setWorkspaceRequired($workspace === null);
                } else {
                    abort(403, 'Access denied: No active tenant workspace is assigned to this account.');
                }
            } else {
                $this->tenantContext->setTenantId((int) $user->tenant_id);

                // Every request type, JSON included: suspension must also stop API reads and writes.
                // getTenant() excludes soft-deleted tenants, so a deleted workspace is denied too.
                $tenant = $this->tenantContext->getTenant();
                if (! $tenant || ! $tenant->isActive()) {
                    abort(403, 'Access denied: Tenant workspace is suspended or unavailable.');
                }
            }
        }

        return $next($request);
    }

    /**
     * The operator's picked workspace, if it still exists. Soft-deleted tenants are not found,
     * and a stale selection is dropped. Suspended tenants stay selectable for support work.
     */
    private function operatorWorkspace(Request $request): ?Tenant
    {
        if (! $request->hasSession()) {
            return null;
        }

        $tenantId = $request->session()->get(self::WORKSPACE_SESSION_KEY);
        if ($tenantId === null) {
            return null;
        }

        $tenant = Tenant::find($tenantId);
        if (! $tenant) {
            $request->session()->forget(self::WORKSPACE_SESSION_KEY);
        }

        return $tenant;
    }
}
