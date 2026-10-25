<?php

namespace App\Traits;

use App\Exceptions\TenantSecurityException;
use App\Models\Tenant;
use App\Scopes\TenantScope;
use App\Services\TenantContext;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function ($model) {
            $context = app(TenantContext::class);
            $tenant = $context->getTenant();

            if (empty($model->tenant_id)) {
                if ($tenant) {
                    $model->tenant_id = $tenant->id;
                } elseif (app()->runningInConsole() || $context->isSuperuserBypass()) {
                    // Fallback to default tenant (1) in console commands / test seeders without active web context
                    $model->tenant_id = 1;
                } else {
                    throw new TenantSecurityException('Security Violation: Attempted to create tenant entity without authenticated tenant context.');
                }
            } else {
                if ($tenant && (int) $model->tenant_id !== (int) $tenant->id && ! $context->isSuperuserBypass()) {
                    throw new TenantSecurityException('Security Violation: Cross-tenant tampering detected. Tenant ID does not match active context.');
                }
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
