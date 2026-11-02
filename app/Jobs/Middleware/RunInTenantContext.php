<?php

namespace App\Jobs\Middleware;

use App\Exceptions\TenantSecurityException;
use App\Models\Tenant;
use App\Services\TenantContext;
use Closure;

/**
 * Runs a queued job as the tenant that owns its model. A job whose tenant is
 * gone (soft-deleted), suspended, or unknown fails instead of running: in a
 * worker there is no other tenant scope to fall back on.
 */
class RunInTenantContext
{
    public function __construct(
        private ?int $tenantId
    ) {}

    public function handle(object $job, Closure $next): mixed
    {
        $tenant = $this->tenantId ? Tenant::find($this->tenantId) : null;

        if (! $tenant || ! $tenant->isActive()) {
            throw new TenantSecurityException("Queued job refused: tenant [{$this->tenantId}] is missing or inactive.");
        }

        return app(TenantContext::class)->runAs($tenant, fn () => $next($job));
    }
}
